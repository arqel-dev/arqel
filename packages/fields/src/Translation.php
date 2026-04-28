<?php

declare(strict_types=1);

namespace Arqel\Fields;

/**
 * Mutable accumulator used by `ValidationBridge` rule translators.
 *
 * Holds the inferred Zod base type (`z.string()`, `z.number()`, …)
 * plus an ordered list of chained calls (`.email()`, `.min(3)`, …).
 * `markRequired()` adds a `.min(1)` to string types so empty strings
 * fail and forces non-string types to keep their non-nullable shape.
 *
 * `nullable` is special: it must come last so `z.string().min(1).nullable()`
 * still allows `null`. The accumulator records "isNullable" and emits
 * the chain at the end.
 */
final class Translation
{
    private string $type = 'z.string()';

    private bool $typeLocked = false;

    /** @var array<int, string> */
    private array $chain = [];

    private bool $required = false;

    private bool $nullable = false;

    public function setType(string $type): void
    {
        $this->type = $type;
        $this->typeLocked = true;
    }

    public function ensureType(string $fallback): void
    {
        if (! $this->typeLocked) {
            $this->type = $fallback;
            $this->typeLocked = true;
        }
    }

    public function addChain(string $segment): void
    {
        if ($segment === '.nullable()') {
            $this->nullable = true;

            return;
        }
        $this->chain[] = $segment;
    }

    public function markRequired(): void
    {
        $this->required = true;
    }

    public function toString(): string
    {
        $chain = $this->chain;

        if ($this->required && $this->type === 'z.string()' && ! in_array('.min(1)', $chain, true)) {
            array_unshift($chain, '.min(1)');
        }

        $output = $this->type.implode('', $chain);

        if ($this->nullable) {
            $output .= '.nullable()';
        }

        return $output;
    }
}

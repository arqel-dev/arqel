<?php

declare(strict_types=1);

namespace Arqel\Import;

use Closure;

/**
 * Declarative column descriptor for an {@see Importer}.
 *
 * `make('email')` matches the `email` header in the source file. The
 * optional `fillUsing` closure transforms the raw cell value before
 * validation; `rules` are Laravel validation rules applied per row;
 * `requiredMapping` marks the header as mandatory (a missing header is
 * a setup error, not a per-row error).
 */
final class ImportColumn
{
    private string $label;

    /** @var array<int, mixed> */
    private array $rules = [];

    private ?Closure $fillUsing = null;

    private bool $mappingRequired = false;

    private function __construct(private readonly string $name)
    {
        $this->label = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** @param array<int, mixed> $rules */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function fillUsing(Closure $callback): self
    {
        $this->fillUsing = $callback;

        return $this;
    }

    public function requiredMapping(bool $required = true): self
    {
        $this->mappingRequired = $required;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /** @return array<int, mixed> */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function isMappingRequired(): bool
    {
        return $this->mappingRequired;
    }

    public function applyFill(?string $raw): mixed
    {
        if ($this->fillUsing === null) {
            return $raw;
        }

        return ($this->fillUsing)($raw);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Closure;
use InvalidArgumentException;

/**
 * Escape-hatch widget — lets app authors render any React component
 * registered on the client without scaffolding a dedicated PHP
 * subclass. The component name is configured at construction time
 * (or via `component()`); the data payload is configured via
 * `withData($payloadOrClosure)` and resolved lazily.
 *
 * Example:
 *   CustomWidget::make('onboarding', 'OnboardingProgressWidget')
 *       ->heading('Onboarding')
 *       ->withData(fn () => ['steps' => StepRepo::progress()]);
 *
 * Note: the spec described the setter as `data(...)` for symmetry
 * with the inherited `data()` accessor, but PHP's LSP rules prevent
 * a subclass from changing the return type of `data(): array` to
 * `static`. The setter is therefore named `withData()` while the
 * accessor `data(): array` continues to satisfy the abstract
 * contract on `Widget`.
 */
final class CustomWidget extends Widget
{
    protected string $type = 'custom';

    /** @var array<string, mixed>|Closure */
    protected array|Closure $payload = [];

    public static function make(string $name, string $component): self
    {
        return (new self($name))->component($component);
    }

    public function component(string $component): static
    {
        if (trim($component) === '') {
            throw new InvalidArgumentException('CustomWidget component name must be a non-empty string.');
        }

        $this->component = $component;

        return $this;
    }

    /**
     * Configure the data payload emitted to the React component.
     *
     * Accepts either an array or a Closure returning an array;
     * Closures are resolved when `data()` is invoked. Non-array
     * Closure return values fall back to an empty array.
     *
     * @param array<string, mixed>|Closure $payload
     */
    public function withData(array|Closure $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Resolve the configured payload, satisfying the abstract
     * `Widget::data()` contract.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        if ($this->payload instanceof Closure) {
            $value = ($this->payload)();

            if (! is_array($value)) {
                return [];
            }

            $resolved = [];
            foreach ($value as $key => $entry) {
                $resolved[(string) $key] = $entry;
            }

            return $resolved;
        }

        return $this->payload;
    }
}

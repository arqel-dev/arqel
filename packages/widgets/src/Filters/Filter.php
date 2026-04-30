<?php

declare(strict_types=1);

namespace Arqel\Widgets\Filters;

use Illuminate\Support\Str;

/**
 * Base class for declarative dashboard-level filters.
 *
 * A `Filter` describes a single control rendered in the dashboard
 * toolbar (date range, select, etc.). Filters are owned by the
 * `Dashboard` and propagated as values into each `Widget` at
 * resolve time, so widget `data()` methods can read them via
 * `Widget::filterValue($name, $default)`.
 *
 * Subclasses are expected to be `final` and to declare:
 * - `protected string $type` — snake_case identifier (e.g. `date_range`)
 * - `protected string $component` — PascalCase React component name
 *
 * Each subclass returns its own type-specific props through
 * `getTypeSpecificProps()`; the canonical envelope is assembled
 * by `toArray()` here.
 */
abstract class Filter
{
    /**
     * The filter type identifier (`date_range`, `select`, ...).
     * Subclasses must override.
     */
    protected string $type = '';

    /**
     * The React component name responsible for rendering the
     * filter. Subclasses must override.
     */
    protected string $component = '';

    protected string $label;

    protected mixed $default = null;

    final public function __construct(protected readonly string $name)
    {
        $this->label = Str::of($name)
            ->snake()
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

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

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    /**
     * Subclass hook: extra payload merged into `toArray()`. Keep
     * keys flat (no nesting) so the React side reads them directly
     * off the envelope.
     *
     * @return array<string, mixed>
     */
    abstract protected function getTypeSpecificProps(): array;

    /**
     * Canonical serialiser. Returns the envelope:
     * `{name, type, component, label, default, ...typeSpecificProps}`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'name' => $this->name,
            'type' => $this->type,
            'component' => $this->component,
            'label' => $this->label,
            'default' => $this->resolveDefault(),
        ], $this->getTypeSpecificProps());
    }

    /**
     * Default values may be Closures (resolved at serialisation
     * time). Subclasses that need to massage the default (e.g.
     * `DateTimeInterface` ↦ ISO 8601 string) should override.
     */
    protected function resolveDefault(): mixed
    {
        return $this->default;
    }
}

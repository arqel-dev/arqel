<?php

declare(strict_types=1);

namespace Arqel\Form\Layout;

use Arqel\Fields\Field;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every form layout component (Section, Fieldset,
 * Grid, Columns, Group, Tabs, Tab).
 *
 * Layout components hold a heterogeneous schema (fields + nested
 * components), declare a column span for placement inside the
 * outer grid, and accept per-record visibility predicates that
 * the controller (CORE-006) evaluates when serialising the
 * payload.
 */
abstract class Component
{
    protected string $type;

    protected string $component;

    /** @var array<int, Component|Field> */
    protected array $schema = [];

    protected int|string $columnSpan = 1;

    protected ?Closure $visibleIf = null;

    protected ?Closure $canSee = null;

    /**
     * @param array<int, Component|Field> $schema
     */
    public function schema(array $schema): static
    {
        $this->schema = array_values($schema);

        return $this;
    }

    public function columnSpan(int|string $span): static
    {
        $this->columnSpan = $span;

        return $this;
    }

    public function visibleIf(Closure $callback): static
    {
        $this->visibleIf = $callback;

        return $this;
    }

    public function canSee(Closure $callback): static
    {
        $this->canSee = $callback;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getComponentName(): string
    {
        return $this->component;
    }

    /** @return array<int, Component|Field> */
    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getColumnSpan(): int|string
    {
        return $this->columnSpan;
    }

    public function isVisibleFor(?Model $record = null): bool
    {
        if ($this->canSee !== null && ! ($this->canSee)($record)) {
            return false;
        }

        if ($this->visibleIf !== null) {
            return (bool) ($this->visibleIf)($record);
        }

        return true;
    }

    /**
     * Serialise the component's own properties (without descending
     * the schema). Subclasses extend this with their type-specific
     * props.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'component' => $this->component,
            'columnSpan' => $this->columnSpan,
            'props' => $this->getTypeSpecificProps(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [];
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Form;

use Arqel\Fields\Field;
use Arqel\Form\Layout\Component;
use Illuminate\Database\Eloquent\Model;

/**
 * Fluent builder for an Arqel form.
 *
 * Holds the declarative schema (a heterogeneous list of layout
 * components and fields) plus form-level configuration (columns,
 * inline rendering, disabled flag, optional bound model class).
 *
 * `getFields()` flattens the schema recursively so validation and
 * controllers have a flat list of fields without losing the
 * layout tree used for rendering.
 *
 * `toArray()` serialises the schema for the Inertia payload. Per-
 * field auth (`canSee`/`canEdit`) and per-field visibility
 * (`isVisibleIn`) are respected by the controller (CORE-006) when
 * the payload is materialised; the Form itself does not filter.
 */
final class Form
{
    /** @var array<int, Component|Field> */
    protected array $schema = [];

    protected int $columns = 1;

    /** @var class-string<Model>|null */
    protected ?string $model = null;

    protected bool $inline = false;

    protected bool $disabled = false;

    public static function make(): self
    {
        return new self;
    }

    /**
     * @param array<int, Component|Field> $schema
     */
    public function schema(array $schema): self
    {
        $this->schema = array_values($schema);

        return $this;
    }

    public function columns(int $columns): self
    {
        $this->columns = max(1, $columns);

        return $this;
    }

    /**
     * @param class-string<Model> $model
     */
    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function inline(bool $inline = true): self
    {
        $this->inline = $inline;

        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    /** @return array<int, Component|Field> */
    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    /** @return class-string<Model>|null */
    public function getModel(): ?string
    {
        return $this->model;
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Flatten the schema into a list of fields, descending through
     * any layout components recursively.
     *
     * @return array<int, Field>
     */
    public function getFields(): array
    {
        return self::flatten($this->schema);
    }

    /**
     * @param array<int, Component|Field> $items
     *
     * @return array<int, Field>
     */
    private static function flatten(array $items): array
    {
        $fields = [];

        foreach ($items as $item) {
            if ($item instanceof Field) {
                $fields[] = $item;

                continue;
            }

            if ($item instanceof Component) {
                foreach (self::flatten($item->getSchema()) as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Serialise the form for the Inertia payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = [];
        foreach ($this->schema as $item) {
            if ($item instanceof Field) {
                $schema[] = [
                    'kind' => 'field',
                    'name' => $item->getName(),
                    'type' => $item->getType(),
                ];
            } elseif ($item instanceof Component) {
                $schema[] = [
                    'kind' => 'layout',
                    ...$item->toArray(),
                ];
            }
        }

        return [
            'schema' => $schema,
            'columns' => $this->columns,
            'model' => $this->model,
            'inline' => $this->inline,
            'disabled' => $this->disabled,
        ];
    }
}

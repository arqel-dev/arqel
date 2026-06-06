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
 * layout tree used for rendering. It accepts the current record so
 * layout-level visibility (`canSee`/`visibleIf`) is honoured: a
 * field whose only guard is an enclosing hidden layout is pruned
 * from the flat list (and therefore never leaks to render or write).
 *
 * `toArray()` serialises the schema for the Inertia payload and skips
 * any layout component the record cannot see (alongside its children).
 * Per-field auth (`canSee`/`canEdit`) is still enforced downstream by
 * the controller (CORE-006); the Form only filters layout containers.
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
     * any layout components recursively. Fields enclosed by a layout
     * the given record cannot see (`canSee`/`visibleIf`) are pruned,
     * so the flat list never exposes them to render or write.
     *
     * @return array<int, Field>
     */
    public function getFields(?Model $record = null): array
    {
        return self::flatten($this->schema, $record);
    }

    /**
     * @param array<int, Component|Field> $items
     *
     * @return array<int, Field>
     */
    private static function flatten(array $items, ?Model $record = null): array
    {
        $fields = [];

        foreach ($items as $item) {
            if ($item instanceof Field) {
                $fields[] = $item;

                continue;
            }

            if ($item instanceof Component) {
                if (! $item->isVisibleFor($record)) {
                    continue;
                }

                foreach (self::flatten($item->getSchema(), $record) as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Serialise the form for the Inertia payload. Layout components the
     * given record cannot see are omitted entirely (with their children),
     * so a field guarded only by an enclosing hidden layout never reaches
     * the client.
     *
     * @return array<string, mixed>
     */
    public function toArray(?Model $record = null): array
    {
        return [
            'schema' => $this->serializeSchema($this->schema, $record),
            'columns' => $this->columns,
            'model' => $this->model,
            'inline' => $this->inline,
            'disabled' => $this->disabled,
        ];
    }

    /**
     * Recursively serialise a schema array (top-level or component
     * children). Components emit `entry.schema` populated with their
     * children; `Component::toArray()` itself remains "without
     * descending the schema" — Form descends, Component only emits
     * its own props. A component the record cannot see is skipped.
     *
     * @param array<int, Component|Field> $items
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeSchema(array $items, ?Model $record = null): array
    {
        $entries = [];

        foreach ($items as $item) {
            if ($item instanceof Field) {
                $entries[] = [
                    'kind' => 'field',
                    'name' => $item->getName(),
                    'type' => $item->getType(),
                ];
            } elseif ($item instanceof Component) {
                if (! $item->isVisibleFor($record)) {
                    continue;
                }

                $entries[] = [
                    'kind' => 'layout',
                    ...$item->toArray(),
                    'schema' => $this->serializeSchema($item->getSchema(), $record),
                ];
            }
        }

        return $entries;
    }
}

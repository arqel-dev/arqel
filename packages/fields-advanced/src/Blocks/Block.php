<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Blocks;

use Arqel\Core\Support\FieldSchemaSerializer;
use Arqel\Fields\Field;

/**
 * Abstract base class for `BuilderField` blocks. Each concrete
 * subclass declares its own `type()`, `label()`, optional `icon()`
 * and a `schema()` of `Field` instances that drive the inner form
 * rendered by the React `BuilderInput.tsx` component (shipped in a
 * future FIELDS-JS-XXX ticket).
 *
 * Blocks are stateless config carriers: instances hold no data
 * beyond the static metadata + the schema definition, so the
 * default no-arg public constructor is intentionally preserved.
 *
 * @see PLANNING/09-fase-2-essenciais.md §FIELDS-ADV-006
 */
abstract class Block
{
    abstract public static function type(): string;

    abstract public static function label(): string;

    /**
     * Optional Lucide-icon identifier rendered next to the block
     * label in the "Add block" menu. Defaults to `null` so subclasses
     * only override when they actually want an icon.
     */
    public static function icon(): ?string
    {
        return null;
    }

    /**
     * Concrete schema of `Field` instances rendered when an instance
     * of this block is added to the builder. Returning a non-Field
     * entry is a programmer error — the parent `BuilderField` filters
     * them out defensively, but the convention is to always return
     * `Field[]`.
     *
     * @return array<int, Field>
     */
    abstract public function schema(): array;

    /**
     * Serialises the block into the payload consumed by the React
     * `BuilderInput.tsx` component. Each child Field is serialised
     * through the canonical `FieldSchemaSerializer` so it ships the
     * same rich FieldSchema (`{name, type, label, placeholder, props,
     * validation, ...}`) the top-level form fields use.
     *
     * The previous `method_exists($field, 'toArray')` guard never
     * matched — no `Field` defines `toArray()` — so every nested field
     * collapsed to `{name, type}`, dropping options/label/placeholder
     * and leaving a nested SelectField with an empty dropdown (#221).
     *
     * @return array{
     *     type: string,
     *     label: string,
     *     icon: ?string,
     *     schema: array<int, array<string, mixed>>
     * }
     */
    final public function toArray(): array
    {
        $fields = array_values(array_filter(
            $this->schema(),
            static fn (mixed $entry): bool => $entry instanceof Field,
        ));

        return [
            'type' => static::type(),
            'label' => static::label(),
            'icon' => static::icon(),
            'schema' => (new FieldSchemaSerializer)->serialize($fields),
        ];
    }
}

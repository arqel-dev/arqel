<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Blocks;

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
     * side. Each child Field is serialised through `toArray()` when
     * available (every concrete `Field` subclass provides one in
     * downstream packages); otherwise we fall back to a minimal
     * `{name, type}` shape so misconfigurations never reach the
     * client as `null`.
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
        return [
            'type' => static::type(),
            'label' => static::label(),
            'icon' => static::icon(),
            'schema' => array_map(
                static function (Field $field): array {
                    if (method_exists($field, 'toArray')) {
                        /** @var array<string, mixed> $payload */
                        $payload = $field->toArray();

                        return $payload;
                    }

                    return [
                        'name' => $field->getName(),
                        'type' => $field->getType(),
                    ];
                },
                array_values(array_filter(
                    $this->schema(),
                    static fn (mixed $entry): bool => $entry instanceof Field,
                )),
            ),
        ];
    }
}

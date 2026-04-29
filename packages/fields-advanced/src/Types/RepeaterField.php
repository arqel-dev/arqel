<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Types;

use Arqel\Fields\Field;
use InvalidArgumentException;

/**
 * Repeater field: an array of nested mini-forms backed (optionally)
 * by an Eloquent HasMany relationship. Each "item" inside the
 * repeater is rendered by the React `RepeaterInput.tsx` component
 * (shipped in a future FIELDS-JS-XXX ticket) using the same
 * `FormRenderer` that drives the parent form, so any leaf field type
 * registered in `FieldFactory` works inside a repeater.
 *
 * **PHP scope of FIELDS-ADV-005 is configuration + payload only.**
 * The cross-package wiring needed to (a) hydrate a repeater from a
 * `HasMany` relation when the form opens and (b) persist nested
 * items via `$record->{relationship}()->create(...)` lives in
 * Resource lifecycle hooks (`afterCreate`/`afterUpdate`) under
 * `arqel/core` and is intentionally deferred.
 *
 * @see PLANNING/09-fase-2-essenciais.md §FIELDS-ADV-005 hydration/persistence
 */
final class RepeaterField extends Field
{
    public const MIN_ITEMS_FLOOR = 0;

    public const MAX_ITEMS_FLOOR = 1;

    protected string $type = 'repeater';

    protected string $component = 'RepeaterInput';

    /** @var array<int, Field> */
    protected array $schema = [];

    protected ?int $minItems = null;

    protected ?int $maxItems = null;

    protected bool $reorderable = true;

    protected bool $collapsible = false;

    protected bool $cloneable = true;

    protected ?string $itemLabel = null;

    protected ?string $relationship = null;

    /**
     * Convenience constructor mirroring the `BelongsToField::make`
     * pattern. Equivalent to `new RepeaterField($name)` but reads
     * naturally as `RepeaterField::make('addresses')->schema([...])`.
     */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Set the nested schema. Non-Field entries are silently filtered
     * so a misconfiguration in PHP never reaches the React side. An
     * empty schema is allowed: the React component falls back to the
     * parent form's fields when no nested schema is declared.
     *
     * @param array<int, mixed> $fields
     */
    public function schema(array $fields): static
    {
        $this->schema = array_values(array_filter(
            $fields,
            static fn (mixed $field): bool => $field instanceof Field,
        ));

        return $this;
    }

    /**
     * Clamp to ≥0 so the React side never receives a negative
     * minimum (which would make Add/Remove arithmetic underflow).
     */
    public function minItems(int $min): static
    {
        $this->minItems = max(self::MIN_ITEMS_FLOOR, $min);

        return $this;
    }

    /**
     * Clamp to ≥1 (a repeater capped at 0 makes no sense) and
     * preserve the `min ≤ max` invariant: passing a max smaller
     * than the current min raises immediately rather than letting
     * the misconfiguration surface client-side.
     */
    public function maxItems(int $max): static
    {
        $clamped = max(self::MAX_ITEMS_FLOOR, $max);

        if ($this->minItems !== null && $clamped < $this->minItems) {
            throw new InvalidArgumentException('maxItems must be >= minItems');
        }

        $this->maxItems = $clamped;

        return $this;
    }

    public function reorderable(bool $enable = true): static
    {
        $this->reorderable = $enable;

        return $this;
    }

    public function collapsible(bool $enable = true): static
    {
        $this->collapsible = $enable;

        return $this;
    }

    public function cloneable(bool $enable = true): static
    {
        $this->cloneable = $enable;

        return $this;
    }

    /**
     * Template rendered as the collapsed-item header. Tokens
     * `{{fieldname}}` are interpolated client-side from each item's
     * state (e.g., `"Address {{label}}"` → `"Address Home"`).
     */
    public function itemLabel(string $template): static
    {
        $this->itemLabel = $template;

        return $this;
    }

    /**
     * Bind the repeater to an Eloquent HasMany relationship name.
     * Hydration/persistence using this binding lives in Resource
     * lifecycle hooks (see class docblock).
     */
    public function relationship(string $name): static
    {
        $this->relationship = $name;

        return $this;
    }

    /**
     * @return array{
     *     schema: array<int, array<string, mixed>>,
     *     minItems: ?int,
     *     maxItems: ?int,
     *     reorderable: bool,
     *     collapsible: bool,
     *     cloneable: bool,
     *     itemLabel: ?string,
     *     relationship: ?string
     * }
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'schema' => array_map(
                static function (Field $child): array {
                    if (method_exists($child, 'toArray')) {
                        /** @var array<string, mixed> $payload */
                        $payload = $child->toArray();

                        return $payload;
                    }

                    return [
                        'name' => $child->getName(),
                        'type' => $child->getType(),
                    ];
                },
                $this->schema,
            ),
            'minItems' => $this->minItems,
            'maxItems' => $this->maxItems,
            'reorderable' => $this->reorderable,
            'collapsible' => $this->collapsible,
            'cloneable' => $this->cloneable,
            'itemLabel' => $this->itemLabel,
            'relationship' => $this->relationship,
        ];
    }
}

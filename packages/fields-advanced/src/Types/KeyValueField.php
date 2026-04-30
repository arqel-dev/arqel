<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Types;

use Arqel\Fields\Field;
use InvalidArgumentException;

/**
 * Key-value editor field powered by `KeyValueInput.tsx` on the React side
 * (shipped in a future FIELDS-JS-XXX ticket). Useful for editing flat
 * metadata maps such as HTTP headers, environment-style config flags
 * or arbitrary tag dictionaries inside a Resource form.
 *
 * The PHP side is configuration-only: it advertises the column labels
 * and placeholders, the toggles for editable keys / add / delete /
 * reorder, and the output shape (`asObject`) that the React component
 * uses to serialise the value before submit. Persistence shape is the
 * consumer's responsibility — pair this with an Eloquent `array` cast
 * (or a JSON column) on the underlying attribute.
 *
 * Output shape switch:
 *
 * - `asObject(false)` (default) — emits a list of `{key, value}` objects
 *   so order is stable and duplicate keys are tolerated client-side.
 * - `asObject(true)` — emits an associative `{key: value}` map; the
 *   React component collapses duplicate keys (last-wins).
 *
 * @see PLANNING/09-fase-2-essenciais.md §FIELDS-ADV-007
 */
final class KeyValueField extends Field
{
    protected string $type = 'keyValue';

    protected string $component = 'KeyValueInput';

    protected string $keyLabel = 'Key';

    protected string $valueLabel = 'Value';

    protected string $keyPlaceholder = '';

    protected string $valuePlaceholder = '';

    protected bool $editableKeys = true;

    protected bool $addable = true;

    protected bool $deletable = true;

    protected bool $reorderable = false;

    protected bool $asObject = false;

    /**
     * Convenience constructor mirroring the `RepeaterField::make`
     * pattern. Equivalent to `new KeyValueField($name)` but reads
     * naturally as `KeyValueField::make('headers')->keyLabel(...)`.
     */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Set the column label for the key cell. Empty strings are rejected
     * because a missing header would render an unlabelled column on the
     * React side, which confuses screen readers.
     */
    public function keyLabel(string $label): static
    {
        if ($label === '') {
            throw new InvalidArgumentException('keyLabel must not be empty');
        }

        $this->keyLabel = $label;

        return $this;
    }

    /**
     * Set the column label for the value cell. Empty strings are
     * rejected for the same accessibility reason as `keyLabel()`.
     */
    public function valueLabel(string $label): static
    {
        if ($label === '') {
            throw new InvalidArgumentException('valueLabel must not be empty');
        }

        $this->valueLabel = $label;

        return $this;
    }

    /**
     * Placeholder rendered inside the key input. Empty strings are
     * accepted and signal "no placeholder" to the React side.
     */
    public function keyPlaceholder(string $placeholder): static
    {
        $this->keyPlaceholder = $placeholder;

        return $this;
    }

    /**
     * Placeholder rendered inside the value input. Empty strings are
     * accepted and signal "no placeholder" to the React side.
     */
    public function valuePlaceholder(string $placeholder): static
    {
        $this->valuePlaceholder = $placeholder;

        return $this;
    }

    public function editableKeys(bool $enable = true): static
    {
        $this->editableKeys = $enable;

        return $this;
    }

    public function addable(bool $enable = true): static
    {
        $this->addable = $enable;

        return $this;
    }

    public function deletable(bool $enable = true): static
    {
        $this->deletable = $enable;

        return $this;
    }

    public function reorderable(bool $enable = true): static
    {
        $this->reorderable = $enable;

        return $this;
    }

    /**
     * Toggle the output shape. When `true`, the React side emits an
     * associative `{key: value}` map (duplicate keys collapse,
     * last-wins). When `false` (default) it emits an ordered list of
     * `{key, value}` objects, preserving order and tolerating
     * duplicates.
     */
    public function asObject(bool $enable = true): static
    {
        $this->asObject = $enable;

        return $this;
    }

    /**
     * @return array{
     *     keyLabel: string,
     *     valueLabel: string,
     *     keyPlaceholder: string,
     *     valuePlaceholder: string,
     *     editableKeys: bool,
     *     addable: bool,
     *     deletable: bool,
     *     reorderable: bool,
     *     asObject: bool
     * }
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'keyLabel' => $this->keyLabel,
            'valueLabel' => $this->valueLabel,
            'keyPlaceholder' => $this->keyPlaceholder,
            'valuePlaceholder' => $this->valuePlaceholder,
            'editableKeys' => $this->editableKeys,
            'addable' => $this->addable,
            'deletable' => $this->deletable,
            'reorderable' => $this->reorderable,
            'asObject' => $this->asObject,
        ];
    }
}

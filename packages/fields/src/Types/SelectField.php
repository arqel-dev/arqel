<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;
use Closure;

/**
 * Single-choice select input.
 *
 * Options can be:
 * - **Static array**: `options(['draft' => 'Draft', 'published' => 'Published'])`
 * - **Closure**: `options(fn () => Category::pluck('name', 'id')->all())`
 * - **Eloquent relationship**: `optionsRelationship('category', 'name')`
 *   The relationship lookup runs in the owner Resource context and is
 *   resolved by the controller (CORE-006); today the field stores the
 *   relation name and display attribute so the controller can pick
 *   them up later.
 *
 * `searchable()` switches the React component to a combobox. `native()`
 * forces the browser-native `<select>` for accessibility-critical
 * paths. `multiple()` flips to a multi-value array; `MultiSelectField`
 * is a thin subclass with that flag set by default.
 *
 * `creatable()` + `createOptionUsing()` allow inline option creation;
 * the controller exposes the callback through a generated POST route
 * once CORE-006 lands.
 */
class SelectField extends Field
{
    protected string $type = 'select';

    protected string $component = 'SelectInput';

    /** @var array<int|string, mixed>|null */
    protected ?array $staticOptions = null;

    protected ?Closure $optionsCallback = null;

    protected ?string $optionsRelation = null;

    protected ?string $optionsRelationDisplay = null;

    protected ?Closure $optionsRelationQuery = null;

    protected bool $searchable = false;

    protected bool $multiple = false;

    protected bool $native = true;

    protected bool $creatable = false;

    protected ?Closure $createUsing = null;

    protected bool $allowCustomValues = false;

    /**
     * @param array<int|string, mixed>|Closure $options
     */
    public function options(array|Closure $options): static
    {
        if ($options instanceof Closure) {
            $this->optionsCallback = $options;
            $this->staticOptions = null;
        } else {
            $this->staticOptions = $options;
            $this->optionsCallback = null;
        }
        $this->optionsRelation = null;
        $this->optionsRelationDisplay = null;
        $this->optionsRelationQuery = null;

        return $this;
    }

    public function optionsRelationship(string $relation, string $display, ?Closure $query = null): static
    {
        $this->optionsRelation = $relation;
        $this->optionsRelationDisplay = $display;
        $this->optionsRelationQuery = $query;
        $this->staticOptions = null;
        $this->optionsCallback = null;

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function native(bool $native = true): static
    {
        $this->native = $native;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function creatable(bool $creatable = true): static
    {
        $this->creatable = $creatable;

        return $this;
    }

    public function createOptionUsing(Closure $callback): static
    {
        $this->createUsing = $callback;
        $this->creatable = true;

        return $this;
    }

    public function allowCustomValues(bool $allow = true): static
    {
        $this->allowCustomValues = $allow;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function getOptionsRelation(): ?string
    {
        return $this->optionsRelation;
    }

    public function getOptionsRelationDisplay(): ?string
    {
        return $this->optionsRelationDisplay;
    }

    public function getOptionsRelationQuery(): ?Closure
    {
        return $this->optionsRelationQuery;
    }

    public function getCreateUsing(): ?Closure
    {
        return $this->createUsing;
    }

    /**
     * Resolve the option list for serialisation.
     *
     * Static options are returned as-is. Closure options are invoked
     * once with no arguments. Relationship options are NOT resolved
     * here — they need owner-Resource context and are filled in by
     * the controller (CORE-006). When unresolved, an empty array is
     * returned so the React side renders an empty list rather than
     * crashing.
     *
     * @return array<int|string, mixed>
     */
    public function resolveOptions(): array
    {
        if ($this->staticOptions !== null) {
            return $this->staticOptions;
        }

        if ($this->optionsCallback !== null) {
            $resolved = ($this->optionsCallback)();

            return is_array($resolved) ? $resolved : [];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [
            'options' => $this->resolveOptions(),
            'searchable' => $this->searchable,
            'multiple' => $this->multiple,
            'native' => $this->native,
            'creatable' => $this->creatable,
            'allowCustomValues' => $this->allowCustomValues,
            'optionsRelation' => $this->optionsRelation,
        ];
    }
}

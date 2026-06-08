<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Stringable;
use Throwable;

/**
 * Single-choice select input.
 *
 * Options can be:
 * - **Static array**: `options(['draft' => 'Draft', 'published' => 'Published'])`
 * - **Closure**: `options(fn () => Category::pluck('name', 'id')->all())`
 * - **Eloquent relationship**: `optionsRelationship('category', 'name')`
 *   The relationship lookup needs the owner Resource model context, so
 *   the field stores the relation name + display attribute and the
 *   serialiser resolves them through {@see resolveOptionsForOwner()}
 *   against the owning model just before the payload is emitted.
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
     * here — they need the owner model context that only the
     * serialiser has, so {@see resolveOptionsForOwner()} does that
     * resolution. When the option source is a relationship (or
     * unset), an empty array is returned so the React side renders an
     * empty list rather than crashing.
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
     * Resolve relationship options against the owning model.
     *
     * Given the Resource model that declares the relation (e.g. a
     * `Post` whose `category()` relation points at `Category`), this
     * loads the related model's query, applies the optional
     * `optionsRelationQuery` constraint, and plucks
     * `display => key` so the result is the same `{key: label}` map
     * shape the React `<SelectInput>` already consumes for static
     * options.
     *
     * Returns an empty array when no relationship is configured, the
     * relation does not resolve to an Eloquent relation, or the query
     * fails for any reason — so a misconfigured field degrades to an
     * empty list rather than throwing during payload assembly.
     *
     * @return array<int|string, string>
     */
    public function resolveOptionsForOwner(Model $owner): array
    {
        if ($this->optionsRelation === null || $this->optionsRelationDisplay === null) {
            return [];
        }

        if (! method_exists($owner, $this->optionsRelation)) {
            return [];
        }

        try {
            $relation = $owner->{$this->optionsRelation}();

            if (! $relation instanceof Relation) {
                return [];
            }

            $related = $relation->getRelated();
            $query = $related->newQuery();

            if ($this->optionsRelationQuery !== null) {
                ($this->optionsRelationQuery)($query);
            }

            $keyName = $related->getKeyName();

            /** @var array<int|string, mixed> $raw */
            $raw = $query
                ->pluck($this->optionsRelationDisplay, $keyName)
                ->all();

            $options = [];
            foreach ($raw as $key => $label) {
                $options[$key] = $this->stringifyOptionLabel($label);
            }

            return $options;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Coerce a plucked display value into a string label, tolerating
     * the `mixed` Eloquent attribute type. Scalars and `Stringable`
     * objects stringify directly; anything else degrades to an empty
     * string rather than throwing during payload assembly.
     */
    private function stringifyOptionLabel(mixed $label): string
    {
        if (is_string($label)) {
            return $label;
        }

        if (is_scalar($label) || $label instanceof Stringable) {
            return (string) $label;
        }

        return '';
    }

    /**
     * Derive validation rules from the option set.
     *
     * A closed-option select (one whose value must be one of the
     * declared option keys) contributes an `in:` rule so tampered
     * requests cannot persist out-of-range values — mirroring what
     * the rendered `<select>` already enforces client-side.
     *
     * The rule is emitted only when:
     * - options are **statically** known (a literal array — closure
     *   and relationship options are resolved with runtime/owner
     *   context the field does not have here, so they degrade
     *   gracefully to no rule rather than a wrong one), AND
     * - the select is not `creatable()` and does not
     *   `allowCustomValues()` (both opt out of the closed set).
     *
     * For `multiple()` selects the field value is an array, so the
     * top-level rule is `array` and the per-element `in:` constraint
     * is exposed via {@see getNestedValidationRules()} under
     * `{name}.*`.
     *
     * @return array<int, string>
     */
    public function getDefaultRules(): array
    {
        if ($this->multiple) {
            return ['array'];
        }

        $inRule = $this->inRuleFromStaticOptions();

        return $inRule !== null ? [$inRule] : [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getNestedValidationRules(): array
    {
        if (! $this->multiple) {
            return [];
        }

        $inRule = $this->inRuleFromStaticOptions();

        if ($inRule === null) {
            return [];
        }

        return [$this->getName().'.*' => [$inRule]];
    }

    /**
     * Build an `in:key1,key2,...` rule from statically-known option
     * keys, or null when the option set is open (creatable /
     * allowCustomValues) or not statically resolvable (closure /
     * relationship / empty).
     */
    protected function inRuleFromStaticOptions(): ?string
    {
        if ($this->creatable || $this->allowCustomValues) {
            return null;
        }

        if ($this->staticOptions === null || $this->staticOptions === []) {
            return null;
        }

        $keys = array_map(
            static fn (int|string $key): string => (string) $key,
            array_keys($this->staticOptions),
        );

        return 'in:'.implode(',', $keys);
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

<?php

declare(strict_types=1);

namespace Arqel\Core\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

/**
 * Serialise an `Arqel\Fields\Field` (or any structurally-compatible
 * object) into the rich JSON shape consumed by the React renderer
 * (`06-api-react.md` §4).
 *
 * The serialiser is intentionally duck-typed: `arqel-dev/core` does
 * not depend on `arqel-dev/fields`, so each accessor is guarded with
 * `method_exists` and falls back when the source object does not
 * implement it. The serializer is the single source of truth for
 * the payload shape — controllers should defer to it rather than
 * hand-rolling per-field arrays.
 *
 * `serialize(fields, ?record, ?user, ?owner, ?resourceSlug)`:
 *   - filters fields by `canBeSeenBy(user, record)` when present
 *   - resolves `isReadonly` and combines with `canBeEditedBy` to
 *     produce a single `readonly` flag
 *   - emits the canonical shape with `validation`, `visibility`,
 *     `dependsOn`, and per-type `props`.
 *   - resolves relationship-backed select options (`#204`) against
 *     the owner model so `props.options` arrives populated. The owner
 *     defaults to `record`; the create page passes a fresh model
 *     instance so options resolve even with no record yet.
 *   - when `$resourceSlug` is given, injects the relationship endpoint
 *     URLs that depend on the owning Resource + panel routing (e.g. a
 *     searchable `BelongsToField`'s `searchRoute`, #203) — these can't
 *     be produced by the Field in isolation.
 */
final class FieldSchemaSerializer
{
    /**
     * @param array<int, mixed> $fields
     *
     * @return list<array<string, mixed>>
     */
    public function serialize(array $fields, ?Model $record = null, ?Authenticatable $user = null, ?Model $owner = null, ?string $resourceSlug = null): array
    {
        // The owner model is the Resource model that declares any
        // relationship-backed options. It defaults to the record being
        // edited/shown; on create the caller passes a fresh instance so
        // `optionsRelationship()` selects still resolve (#204).
        $owner ??= $record;

        $serialized = [];
        foreach ($fields as $field) {
            if (! is_object($field)) {
                continue;
            }

            if (! $this->isVisibleFor($field, $record, $user)) {
                continue;
            }

            $serialized[] = $this->serializeOne($field, $record, $user, $owner, $resourceSlug);
        }

        return $serialized;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOne(object $field, ?Model $record, ?Authenticatable $user, ?Model $owner = null, ?string $resourceSlug = null): array
    {
        return [
            'type' => $this->call($field, 'getType') ?? '',
            'name' => $this->call($field, 'getName') ?? '',
            'label' => $this->call($field, 'getLabel'),
            'component' => $this->call($field, 'getComponent'),
            'required' => $this->isRequired($field),
            'readonly' => $this->isReadonly($field, $record, $user),
            'disabled' => $this->isDisabled($field, $record),
            'placeholder' => $this->call($field, 'getPlaceholder'),
            'helperText' => $this->call($field, 'getHelperText'),
            'defaultValue' => $this->call($field, 'getDefault'),
            'columnSpan' => $this->call($field, 'getColumnSpan') ?? 1,
            'live' => (bool) ($this->call($field, 'isLive') ?? false),
            'liveDebounce' => $this->call($field, 'getLiveDebounce'),
            'validation' => $this->serializeValidation($field),
            'visibility' => $this->serializeVisibility($field, $record, $user),
            'dependsOn' => $this->serializeDependencies($field),
            'props' => $this->serializeProps($field, $owner, $resourceSlug),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeValidation(object $field): array
    {
        $rules = method_exists($field, 'getValidationRules') ? $field->getValidationRules() : [];
        $messages = method_exists($field, 'getValidationMessages') ? $field->getValidationMessages() : [];
        $attribute = method_exists($field, 'getValidationAttribute') ? $field->getValidationAttribute() : null;

        return [
            'rules' => $this->stringifyRules(is_array($rules) ? array_values($rules) : []),
            'messages' => is_array($messages) ? $messages : [],
            'attribute' => is_string($attribute) ? $attribute : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVisibility(object $field, ?Model $record, ?Authenticatable $user): array
    {
        $contexts = ['create', 'edit', 'detail', 'table'];

        $visible = [];
        foreach ($contexts as $context) {
            $visible[$context] = method_exists($field, 'isVisibleIn')
                ? (bool) $field->isVisibleIn($context, $record)
                : true;
        }

        $visible['canSee'] = $this->isVisibleFor($field, $record, $user);

        return $visible;
    }

    /**
     * @return array<int, string>
     */
    private function serializeDependencies(object $field): array
    {
        if (! method_exists($field, 'getDependencies')) {
            return [];
        }

        $deps = $field->getDependencies();
        if (! is_array($deps)) {
            return [];
        }

        $clean = [];
        foreach ($deps as $dep) {
            if (is_string($dep) && $dep !== '') {
                $clean[] = $dep;
            }
        }

        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProps(object $field, ?Model $owner = null, ?string $resourceSlug = null): array
    {
        if (! method_exists($field, 'getTypeSpecificProps')) {
            return [];
        }

        $props = $field->getTypeSpecificProps();

        if (! is_array($props)) {
            return [];
        }

        $clean = [];
        foreach ($props as $key => $value) {
            $clean[(string) $key] = $value;
        }

        $clean = $this->withResolvedRelationOptions($field, $clean, $owner);

        return $this->injectRelationshipRoutes($field, $clean, $resourceSlug);
    }

    /**
     * Resolve relationship-backed select options against the owner
     * model and inject them into `props.options` (#204).
     *
     * A `SelectField::optionsRelationship('category', 'name')` stores
     * the relation metadata but cannot pluck the options itself — it
     * has no owner-model context. The serialiser does: when the field
     * advertises a relation (`getOptionsRelation() !== null`), exposes
     * `resolveOptionsForOwner()`, and an owner model is available, the
     * resolved `{key: label}` map replaces the empty `props.options`.
     *
     * Static- and closure-mode selects expose no relation, so their
     * `props.options` is left byte-identical. The whole step is
     * duck-typed, so non-Select fields are untouched.
     *
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function withResolvedRelationOptions(object $field, array $props, ?Model $owner): array
    {
        if ($owner === null) {
            return $props;
        }

        if (! method_exists($field, 'getOptionsRelation') || ! method_exists($field, 'resolveOptionsForOwner')) {
            return $props;
        }

        if ($field->getOptionsRelation() === null) {
            return $props;
        }

        $resolved = $field->resolveOptionsForOwner($owner);

        if (is_array($resolved) && $resolved !== []) {
            $props['options'] = $resolved;
        }

        return $props;
    }

    /**
     * Inject the panel-scoped endpoint URLs that a relationship Field
     * cannot build on its own because they depend on the owning
     * Resource slug + named panel routes (#203).
     *
     * For a searchable `BelongsToField`, `BelongsToInput.tsx` is
     * driven entirely by `props.searchRoute`: without it the async
     * picker short-circuits to an empty list and the relation can
     * never be selected. We resolve `arqel.fields.search` here, where
     * the owning Resource slug is known.
     *
     * Duck-typed (core has no `arqel-dev/fields` dep): a field is treated
     * as a searchable BelongsTo when its `props` carry the BelongsTo
     * signature (`relatedResource` + a truthy `searchable`) and it
     * exposes `getName()`. The route is only injected when it is both
     * registered and not already present.
     *
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function injectRelationshipRoutes(object $field, array $props, ?string $resourceSlug): array
    {
        if ($resourceSlug === null || $resourceSlug === '') {
            return $props;
        }

        if (array_key_exists('searchRoute', $props)) {
            return $props;
        }

        $isSearchableBelongsTo = array_key_exists('relatedResource', $props)
            && array_key_exists('searchable', $props)
            && $props['searchable'] === true;

        if (! $isSearchableBelongsTo) {
            return $props;
        }

        if (! method_exists($field, 'getName')) {
            return $props;
        }

        $name = $field->getName();
        if (! is_string($name) || $name === '') {
            return $props;
        }

        if (! Route::has('arqel.fields.search')) {
            return $props;
        }

        $props['searchRoute'] = route('arqel.fields.search', [
            'resource' => $resourceSlug,
            'field' => $name,
        ]);

        return $props;
    }

    private function isVisibleFor(object $field, ?Model $record, ?Authenticatable $user): bool
    {
        if (method_exists($field, 'canBeSeenBy')) {
            return (bool) $field->canBeSeenBy($user, $record);
        }

        return true;
    }

    private function isRequired(object $field): bool
    {
        if (method_exists($field, 'isRequired')) {
            $value = $field->isRequired();

            return is_bool($value) ? $value : (bool) $value;
        }

        $rules = method_exists($field, 'getValidationRules') ? $field->getValidationRules() : [];

        return is_array($rules) && in_array('required', $rules, true);
    }

    private function isReadonly(object $field, ?Model $record, ?Authenticatable $user): bool
    {
        $readonly = method_exists($field, 'isReadonly')
            ? (bool) $field->isReadonly()
            : false;

        if ($readonly) {
            return true;
        }

        if (method_exists($field, 'canBeEditedBy')) {
            return ! (bool) $field->canBeEditedBy($user, $record);
        }

        return false;
    }

    private function isDisabled(object $field, ?Model $record): bool
    {
        if (! method_exists($field, 'isDisabled')) {
            return false;
        }

        $value = $field->isDisabled($record);

        return is_bool($value) ? $value : (bool) $value;
    }

    private function call(object $field, string $method): mixed
    {
        if (! method_exists($field, $method)) {
            return null;
        }

        return $field->{$method}();
    }

    /**
     * @param array<int, mixed> $rules
     *
     * @return list<string>
     */
    private function stringifyRules(array $rules): array
    {
        $clean = [];
        foreach ($rules as $rule) {
            if ($rule instanceof Closure) {
                continue;
            }

            if (is_string($rule)) {
                $clean[] = $rule;

                continue;
            }

            if (is_object($rule)) {
                $clean[] = $rule::class;
            }
        }

        return $clean;
    }
}

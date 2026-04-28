<?php

declare(strict_types=1);

namespace Arqel\Core\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Serialise an `Arqel\Fields\Field` (or any structurally-compatible
 * object) into the rich JSON shape consumed by the React renderer
 * (`06-api-react.md` §4).
 *
 * The serialiser is intentionally duck-typed: `arqel/core` does
 * not depend on `arqel/fields`, so each accessor is guarded with
 * `method_exists` and falls back when the source object does not
 * implement it. The serializer is the single source of truth for
 * the payload shape — controllers should defer to it rather than
 * hand-rolling per-field arrays.
 *
 * `serialize(fields, ?record, ?user)`:
 *   - filters fields by `canBeSeenBy(user, record)` when present
 *   - resolves `isReadonly` and combines with `canBeEditedBy` to
 *     produce a single `readonly` flag
 *   - emits the canonical shape with `validation`, `visibility`,
 *     `dependsOn`, and per-type `props`.
 */
final class FieldSchemaSerializer
{
    /**
     * @param array<int, mixed> $fields
     *
     * @return list<array<string, mixed>>
     */
    public function serialize(array $fields, ?Model $record = null, ?Authenticatable $user = null): array
    {
        $serialized = [];
        foreach ($fields as $field) {
            if (! is_object($field)) {
                continue;
            }

            if (! $this->isVisibleFor($field, $record, $user)) {
                continue;
            }

            $serialized[] = $this->serializeOne($field, $record, $user);
        }

        return $serialized;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOne(object $field, ?Model $record, ?Authenticatable $user): array
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
            'props' => $this->serializeProps($field),
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
    private function serializeProps(object $field): array
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

        return $clean;
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

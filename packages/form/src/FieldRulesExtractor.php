<?php

declare(strict_types=1);

namespace Arqel\Form;

use Arqel\Fields\Field;

/**
 * Aggregate validation rules + custom messages + attribute names
 * across a flat list of Fields.
 *
 * The contract is duck-typed against `Arqel\Fields\Field`:
 *   - `getName(): string`
 *   - `getValidationRules(): array<int, mixed>`           — required
 *   - `getValidationMessage(): ?string`                   — optional
 *   - `getValidationAttribute(): ?string`                 — optional
 *
 * Layouts (Section/Fieldset/Tabs) carry no fields directly — pass
 * the Form's `getFields()` (already flattened) into `extract()`.
 */
final class FieldRulesExtractor
{
    /**
     * @param array<int, mixed> $fields
     *
     * @return array<string, array<int, mixed>>
     */
    public function extract(array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                continue;
            }

            $rules[$field->getName()] = $field->getValidationRules();
        }

        return $rules;
    }

    /**
     * Aggregate per-rule custom messages, namespaced by field
     * (`{name}.{rule}` → message), in the shape Laravel's
     * `FormRequest::messages()` consumes.
     *
     * @param array<int, mixed> $fields
     *
     * @return array<string, string>
     */
    public function extractMessages(array $fields): array
    {
        $messages = [];
        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                continue;
            }

            foreach ($field->getValidationMessages() as $rule => $message) {
                if (! is_string($rule) || ! is_string($message)) {
                    continue;
                }
                $messages[$field->getName().'.'.$rule] = $message;
            }
        }

        return $messages;
    }

    /**
     * @param array<int, mixed> $fields
     *
     * @return array<string, string>
     */
    public function extractAttributes(array $fields): array
    {
        $attributes = [];
        foreach ($fields as $field) {
            if (! $field instanceof Field) {
                continue;
            }

            $attribute = $field->getValidationAttribute();
            if ($attribute !== null && $attribute !== '') {
                $attributes[$field->getName()] = $attribute;
            }
        }

        return $attributes;
    }
}

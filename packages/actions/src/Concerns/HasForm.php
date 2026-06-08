<?php

declare(strict_types=1);

namespace Arqel\Actions\Concerns;

use Arqel\Fields\Field;

/**
 * Form modal config for an Action. The fields are a flat list of
 * `Arqel\Fields\Field` (no Form layout components — Action modals
 * are intentionally simple). Validation rules are aggregated from
 * each field's own rules; the controller (ACTIONS-006) validates
 * the request and forwards the data to `execute(record, data)`.
 */
trait HasForm
{
    public const string MODAL_SIZE_SM = 'sm';

    public const string MODAL_SIZE_MD = 'md';

    public const string MODAL_SIZE_LG = 'lg';

    public const string MODAL_SIZE_XL = 'xl';

    public const string MODAL_SIZE_FULL = 'full';

    /** @var array<int, Field> */
    protected array $form = [];

    protected string $modalSize = self::MODAL_SIZE_MD;

    /**
     * @param array<int, mixed> $fields
     */
    public function form(array $fields): static
    {
        $this->form = array_values(array_filter(
            $fields,
            static fn ($field): bool => $field instanceof Field,
        ));

        return $this;
    }

    public function modalWide(bool $wide = true): static
    {
        $this->modalSize = $wide ? self::MODAL_SIZE_LG : self::MODAL_SIZE_MD;

        return $this;
    }

    public function modalSize(string $size): static
    {
        $this->modalSize = in_array($size, [
            self::MODAL_SIZE_SM,
            self::MODAL_SIZE_MD,
            self::MODAL_SIZE_LG,
            self::MODAL_SIZE_XL,
            self::MODAL_SIZE_FULL,
        ], true)
            ? $size
            : self::MODAL_SIZE_MD;

        return $this;
    }

    public function hasForm(): bool
    {
        return $this->form !== [];
    }

    public function getModalSize(): string
    {
        return $this->modalSize;
    }

    /**
     * @return array<int, Field>
     */
    public function getFormFields(): array
    {
        return $this->form;
    }

    /**
     * Aggregate validation rules across all fields, keyed by name.
     *
     * @return array<string, array<int, mixed>>
     */
    public function getFormValidationRules(): array
    {
        $rules = [];
        foreach ($this->form as $field) {
            $rules[$field->getName()] = $field->getValidationRules();

            foreach ($field->getNestedValidationRules() as $path => $nestedRules) {
                $rules[$path] = $nestedRules;
            }
        }

        return $rules;
    }

    /**
     * Serialise the modal *layout* for the action payload: each field
     * reduced to `{name, type}`, preserving declaration order. The rich
     * per-field payload (options, label, validation, props) is produced
     * separately by `Action::toArray()` via `FieldSchemaSerializer`
     * (CORE-010) under the `formFields` key, and the React renderer joins
     * the two by `name` (#213).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFormSchemaArray(): array
    {
        $schema = [];
        foreach ($this->form as $field) {
            $schema[] = [
                'name' => $field->getName(),
                'type' => $field->getType(),
            ];
        }

        return $schema;
    }
}

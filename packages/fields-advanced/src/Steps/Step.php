<?php

declare(strict_types=1);

namespace Arqel\FieldsAdvanced\Steps;

use Arqel\Core\Support\FieldSchemaSerializer;
use Arqel\Fields\Field;
use Illuminate\Support\Str;

/**
 * Value-object describing a single step inside a `WizardField`. Each
 * step holds a unique `name`, a human-readable `label` (defaults to a
 * humanised version of the name when not set), an optional Lucide
 * `icon`, and the `schema` of `Field` instances rendered when the step
 * is active.
 *
 * Steps are stateless config carriers: instances hold no runtime data
 * beyond the static metadata + the schema definition. The React
 * `WizardInput.tsx` component (shipped under FIELDS-JS-XXX) consumes
 * the `toArray()` payload verbatim and uses `<Activity>` (React 19.2)
 * to preserve previous-step state without remounting subtrees.
 *
 * @see PLANNING/09-fase-2-essenciais.md §FIELDS-ADV-009
 */
final class Step
{
    protected string $name;

    protected string $label = '';

    protected ?string $icon = null;

    /** @var array<int, Field> */
    protected array $schema = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Set the schema of fields rendered when this step is active.
     * Non-`Field` entries are silently filtered so a misconfiguration
     * in PHP never reaches the React side as `null`.
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

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the explicit label when set, otherwise a humanised
     * version of the step name (e.g., `'user_details'` → `'User
     * Details'`). Mirrors the default-label heuristic used by the
     * `Field` base class.
     */
    public function getLabel(): string
    {
        if ($this->label !== '') {
            return $this->label;
        }

        return Str::of($this->name)
            ->snake()
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * @return array<int, Field>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Serialises the step into the payload consumed by the React
     * `WizardInput.tsx` component. Each child field is serialised
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
     *     name: string,
     *     label: string,
     *     icon: ?string,
     *     schema: array<int, array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->getLabel(),
            'icon' => $this->icon,
            'schema' => (new FieldSchemaSerializer)->serialize($this->schema),
        ];
    }
}

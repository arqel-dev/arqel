<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

/**
 * Slug input.
 *
 * `fromField('title')` declares the source attribute for live slug
 * generation in the React layer; the dependency wiring lives in
 * `HasDependencies` (FIELDS-017). Until then, the prop is exposed
 * verbatim in the type-specific props.
 */
final class SlugField extends TextField
{
    protected string $type = 'slug';

    protected string $component = 'SlugInput';

    protected ?string $fromField = null;

    protected string $separator = '-';

    public function fromField(string $field): static
    {
        $this->fromField = $field;

        return $this;
    }

    public function separator(string $separator): static
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            ...parent::getTypeSpecificProps(),
            'fromField' => $this->fromField,
            'separator' => $this->separator,
        ], fn ($value) => $value !== null);
    }
}

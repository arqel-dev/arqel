<?php

declare(strict_types=1);

namespace Arqel\Fields\Types;

use Arqel\Fields\Field;

/**
 * Single-line text input.
 *
 * Subclassed by `EmailField`, `UrlField`, `PasswordField`, and `SlugField`,
 * which override `$type`/`$component` and add a few flags. Constraints
 * declared here surface in `getTypeSpecificProps()` so the React side
 * can render the right HTML attributes (`maxlength`, `pattern`,
 * `autocomplete`, …) and feed validation downstream.
 */
class TextField extends Field
{
    protected string $type = 'text';

    protected string $component = 'TextInput';

    protected ?int $maxLength = null;

    protected ?int $minLength = null;

    protected ?string $pattern = null;

    protected ?string $autocomplete = null;

    protected ?string $mask = null;

    public function maxLength(int $max): static
    {
        $this->maxLength = $max;

        return $this;
    }

    public function minLength(int $min): static
    {
        $this->minLength = $min;

        return $this;
    }

    public function pattern(string $regex): static
    {
        $this->pattern = $regex;

        return $this;
    }

    public function autocomplete(string $token): static
    {
        $this->autocomplete = $token;

        return $this;
    }

    public function mask(string $pattern): static
    {
        $this->mask = $pattern;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return array_filter([
            'maxLength' => $this->maxLength,
            'minLength' => $this->minLength,
            'pattern' => $this->pattern,
            'autocomplete' => $this->autocomplete,
            'mask' => $this->mask,
        ], fn ($value) => $value !== null);
    }
}

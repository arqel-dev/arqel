<?php

declare(strict_types=1);

namespace Arqel\Widgets\Filters;

use Closure;

/**
 * Dropdown filter. Options accept either a static
 * `array<value, label>` map or a Closure resolved at
 * `toArray()` time (so option lists can read DB state on
 * each render without paying that cost at registration).
 */
final class SelectFilter extends Filter
{
    protected string $type = 'select';

    protected string $component = 'SelectFilter';

    /** @var array<int|string, mixed>|Closure */
    protected array|Closure $options = [];

    protected bool $multiple = false;

    /**
     * @param array<int|string, mixed>|Closure $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * @return array{options: array<int|string, mixed>, multiple: bool}
     */
    protected function getTypeSpecificProps(): array
    {
        $options = $this->options;
        if ($options instanceof Closure) {
            $resolved = ($options)();
            $options = is_array($resolved) ? $resolved : [];
        }

        return [
            'options' => $options,
            'multiple' => $this->multiple,
        ];
    }
}

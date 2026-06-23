<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;
use Closure;

/**
 * Editable select cell. Options accept an array or a Closure
 * resolved lazily at `toArray()` time (Closure returning a
 * non-array degrades to an empty list).
 */
final class SelectColumn extends Column
{
    protected string $type = 'select';

    protected bool $editable = true;

    protected int $debounce = 500;

    /** @var array<int, mixed> */
    protected array $rules = [];

    protected bool|Closure $readonly = false;

    /** @var array<int|string, mixed>|Closure */
    protected array|Closure $options = [];

    /**
     * @param array<int|string, mixed>|Closure $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param array<int, mixed> $rules
     */
    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function debounce(int $ms): static
    {
        $this->debounce = max(0, $ms);

        return $this;
    }

    public function readonly(bool|Closure $readonly = true): static
    {
        $this->readonly = $readonly;

        if ($readonly === true) {
            $this->editable = false;
        } elseif ($readonly === false) {
            $this->editable = true;
        }

        return $this;
    }

    public function isEditable(): bool
    {
        return $this->editable;
    }

    public function getDebounce(): int
    {
        return $this->debounce;
    }

    /**
     * @return array<int, mixed>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getReadonly(): bool|Closure
    {
        return $this->readonly;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function resolveOptions(): array
    {
        if ($this->options instanceof Closure) {
            $resolved = ($this->options)();

            return is_array($resolved) ? self::localizeOptionLabels($resolved) : [];
        }

        return self::localizeOptionLabels($this->options);
    }

    /**
     * Pass each option *label* (the map value) through Laravel translation
     * lazily so the active request locale applies at serialization time. A
     * label that is a translation key renders in the current locale; a plain
     * literal passes through unchanged (Laravel __() returns the key when no
     * translation exists). Keys are preserved untouched; non-string labels
     * are left as-is.
     *
     * @param array<int|string, mixed> $options
     *
     * @return array<int|string, mixed>
     */
    private static function localizeOptionLabels(array $options): array
    {
        if (! app()->bound('translator')) {
            return $options;
        }

        $localized = [];
        foreach ($options as $key => $label) {
            if (is_string($label)) {
                $translated = trans($label);
                $localized[$key] = is_string($translated) ? $translated : $label;

                continue;
            }

            $localized[$key] = $label;
        }

        return $localized;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'editable' => $this->editable,
            'debounce' => $this->debounce,
            'rules' => $this->rules,
            'options' => $this->resolveOptions(),
        ]);
    }
}

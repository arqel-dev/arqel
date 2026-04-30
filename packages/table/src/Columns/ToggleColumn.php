<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;
use Closure;

/**
 * Editable toggle cell. `onValue`/`offValue` let apps map the
 * boolean UI to arbitrary persisted values (e.g. `'active'` /
 * `'inactive'` instead of `true` / `false`).
 */
final class ToggleColumn extends Column
{
    protected string $type = 'toggle';

    protected bool $editable = true;

    protected int $debounce = 500;

    /** @var array<int, mixed> */
    protected array $rules = [];

    protected bool|Closure $readonly = false;

    protected mixed $onValue = true;

    protected mixed $offValue = false;

    public function onValue(mixed $value): static
    {
        $this->onValue = $value;

        return $this;
    }

    public function offValue(mixed $value): static
    {
        $this->offValue = $value;

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

    public function getOnValue(): mixed
    {
        return $this->onValue;
    }

    public function getOffValue(): mixed
    {
        return $this->offValue;
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
            'onValue' => $this->onValue,
            'offValue' => $this->offValue,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Table\Columns;

use Arqel\Table\Column;
use Closure;

/**
 * Editable text-input cell. Renders an inline text input on the
 * React side; PHP side exposes the editable contract (rules,
 * debounce, readonly toggle) consumed by the inline-update
 * controller (deferred to a follow-up cross-package ticket).
 */
final class TextInputColumn extends Column
{
    protected string $type = 'textInput';

    protected bool $editable = true;

    protected int $debounce = 500;

    /** @var array<int, mixed> */
    protected array $rules = [];

    protected bool|Closure $readonly = false;

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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'editable' => $this->editable,
            'debounce' => $this->debounce,
            'rules' => $this->rules,
        ]);
    }
}

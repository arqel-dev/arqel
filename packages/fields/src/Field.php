<?php

declare(strict_types=1);

namespace Arqel\Fields;

use Arqel\Fields\Concerns\HasDependencies;
use Arqel\Fields\Concerns\HasValidation;
use Arqel\Fields\Concerns\HasVisibility;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base class for every Arqel Field type.
 *
 * Subclasses (`TextField`, `SelectField`, etc.) declare the concrete
 * `$type` and `$component` and may override `getTypeSpecificProps()`
 * to expose props that the React side consumes. The fluent API is
 * shared across all types so `Field::text('name')->required()
 * ->maxLength(255)` reads naturally regardless of subclass.
 *
 * Concerns (`HasValidation`, `HasVisibility`, `HasDependencies`,
 * `HasAuthorization`) are deliberately NOT applied here yet — they
 * land in FIELDS-015..018 where each gets its own contract and
 * test surface. The core fluent API exposed today covers everything
 * a subclass needs to be useful: label, placeholder, helper text,
 * default value, readonly/disabled, column span, dehydration,
 * reactive live updates, and a state-updated callback hook.
 */
abstract class Field
{
    use HasDependencies;
    use HasValidation;
    use HasVisibility;

    protected string $type;

    protected string $component;

    protected string $name;

    protected ?string $label = null;

    protected ?string $placeholder = null;

    protected ?string $helperText = null;

    protected mixed $default = null;

    protected bool $readonly = false;

    protected bool|Closure $disabled = false;

    protected int|string $columnSpan = 1;

    protected bool|Closure $dehydrated = true;

    protected bool $live = false;

    protected ?int $liveDebounce = null;

    protected ?Closure $afterStateUpdated = null;

    final public function __construct(string $name)
    {
        $this->name = $name;
        $this->label = Str::of($name)
            ->snake()
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function helperText(?string $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    public function readonly(bool $readonly = true): static
    {
        $this->readonly = $readonly;

        return $this;
    }

    public function disabled(bool|Closure $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function columnSpan(int|string $span): static
    {
        $this->columnSpan = $span;

        return $this;
    }

    public function columnSpanFull(): static
    {
        $this->columnSpan = 'full';

        return $this;
    }

    public function dehydrated(bool|Closure $dehydrated = true): static
    {
        $this->dehydrated = $dehydrated;

        return $this;
    }

    public function live(bool $live = true): static
    {
        $this->live = $live;
        if ($live && $this->liveDebounce === null) {
            $this->liveDebounce = 0;
        }

        return $this;
    }

    public function liveDebounced(int $ms = 300): static
    {
        $this->live = true;
        $this->liveDebounce = $ms;

        return $this;
    }

    public function afterStateUpdated(Closure $callback): static
    {
        $this->afterStateUpdated = $callback;
        $this->live = true;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->name;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function getHelperText(): ?string
    {
        return $this->helperText;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function isDisabled(?Model $record = null): bool
    {
        if ($this->disabled instanceof Closure) {
            return (bool) ($this->disabled)($record);
        }

        return $this->disabled;
    }

    public function getColumnSpan(): int|string
    {
        return $this->columnSpan;
    }

    public function isDehydrated(?Model $record = null): bool
    {
        if ($this->dehydrated instanceof Closure) {
            return (bool) ($this->dehydrated)($record);
        }

        return $this->dehydrated;
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    public function getLiveDebounce(): ?int
    {
        return $this->liveDebounce;
    }

    public function getAfterStateUpdated(): ?Closure
    {
        return $this->afterStateUpdated;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [];
    }
}

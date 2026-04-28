<?php

declare(strict_types=1);

namespace Arqel\Table;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base class for every Arqel Table column.
 *
 * Subclasses (`TextColumn`, `BadgeColumn`, …) declare the concrete
 * `$type` property and may override `getTypeSpecificProps()` to
 * expose props the React side renders (`limit`, `colors`, `icons`,
 * etc.). The fluent API on the base class covers the cross-cutting
 * surface: label, sortable, searchable, copyable, hidden,
 * alignment, width, format callback, URL, per-row visibility.
 *
 * The constructor is `final` for the same reason `Field`'s is —
 * subclasses use static factories or property defaults, never a
 * different constructor signature.
 *
 * `formatState` returns the cell value for serialisation. When a
 * `formatStateUsing` callback is registered it receives the raw
 * state and the record. When `getStateUsing` is registered, it
 * computes the value from the record (used by `ComputedColumn`).
 */
abstract class Column
{
    public const string ALIGN_START = 'start';

    public const string ALIGN_CENTER = 'center';

    public const string ALIGN_END = 'end';

    protected string $type;

    protected string $name;

    protected ?string $label = null;

    protected bool $sortable = false;

    protected bool $searchable = false;

    protected bool $copyable = false;

    protected bool $hidden = false;

    protected bool $hiddenOnMobile = false;

    protected ?string $alignment = null;

    protected ?string $width = null;

    protected ?Closure $formatStateUsing = null;

    protected ?Closure $getStateUsing = null;

    protected null|Closure|string $url = null;

    protected bool $openUrlInNewTab = false;

    protected ?Closure $canSee = null;

    protected ?Closure $tooltip = null;

    final public function __construct(string $name)
    {
        $this->name = $name;
        $this->label = Str::of($name)->snake()->replace('_', ' ')->title()->toString();
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function sortable(bool $sortable = true): static
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function copyable(bool $copyable = true): static
    {
        $this->copyable = $copyable;

        return $this;
    }

    public function hidden(bool $hidden = true): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function hiddenOnMobile(bool $hidden = true): static
    {
        $this->hiddenOnMobile = $hidden;

        return $this;
    }

    public function alignStart(): static
    {
        $this->alignment = self::ALIGN_START;

        return $this;
    }

    public function alignCenter(): static
    {
        $this->alignment = self::ALIGN_CENTER;

        return $this;
    }

    public function alignEnd(): static
    {
        $this->alignment = self::ALIGN_END;

        return $this;
    }

    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function formatStateUsing(Closure $callback): static
    {
        $this->formatStateUsing = $callback;

        return $this;
    }

    public function getStateUsing(Closure $callback): static
    {
        $this->getStateUsing = $callback;

        return $this;
    }

    public function url(Closure|string $url, bool $newTab = false): static
    {
        $this->url = $url;
        $this->openUrlInNewTab = $newTab;

        return $this;
    }

    public function canSee(Closure $callback): static
    {
        $this->canSee = $callback;

        return $this;
    }

    public function tooltip(Closure $callback): static
    {
        $this->tooltip = $callback;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->name;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isCopyable(): bool
    {
        return $this->copyable;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function isHiddenOnMobile(): bool
    {
        return $this->hiddenOnMobile;
    }

    public function getAlignment(): ?string
    {
        return $this->alignment;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function getCanSeeCallback(): ?Closure
    {
        return $this->canSee;
    }

    public function isVisibleFor(?Model $record = null): bool
    {
        if ($this->hidden) {
            return false;
        }

        if ($this->canSee === null) {
            return true;
        }

        return (bool) ($this->canSee)($record);
    }

    /**
     * Compute the cell's raw state from the record.
     *
     * Default: `$record->{$this->name}`. `getStateUsing` overrides
     * the lookup (used by `ComputedColumn`).
     */
    public function getState(?Model $record): mixed
    {
        if ($this->getStateUsing !== null) {
            return ($this->getStateUsing)($record);
        }

        if ($record === null) {
            return null;
        }

        return $record->getAttribute($this->name);
    }

    /**
     * Format the cell's raw state into the value the React side
     * renders. `formatStateUsing` overrides the default identity
     * mapping; subclasses can also override this method directly.
     */
    public function formatState(mixed $state, ?Model $record = null): mixed
    {
        if ($this->formatStateUsing !== null) {
            return ($this->formatStateUsing)($state, $record);
        }

        return $state;
    }

    public function resolveUrl(?Model $record = null): ?string
    {
        if ($this->url === null) {
            return null;
        }

        if (is_string($this->url)) {
            return $this->url;
        }

        $resolved = ($this->url)($record);

        return is_string($resolved) ? $resolved : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [];
    }

    /**
     * Serialise the column declaration for the Inertia payload.
     *
     * Per-row state belongs to the row serialisation path in the
     * controller (CORE-006), not here — this is the schema only.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->getLabel(),
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'copyable' => $this->copyable,
            'hidden' => $this->hidden,
            'hiddenOnMobile' => $this->hiddenOnMobile,
            'alignment' => $this->alignment,
            'width' => $this->width,
            'url' => is_string($this->url) ? $this->url : null,
            'openUrlInNewTab' => $this->openUrlInNewTab,
            'props' => $this->getTypeSpecificProps(),
        ];
    }
}

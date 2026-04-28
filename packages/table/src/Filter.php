<?php

declare(strict_types=1);

namespace Arqel\Table;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base class for every Arqel Table filter.
 *
 * Filters declare a UI shape (`type` + `component`-equivalent
 * props), an apply behaviour, and an optional default value. The
 * controller's `TableQueryBuilder` (TABLE-005) routes the request's
 * `?filter[name]=...` value through `applyToQuery()`. Custom logic
 * can be installed via `apply(Closure)` — when set, it is preferred
 * over the subclass's default `applyToQuery()`.
 *
 * Filters are persistable in the URL by default. Apps that want
 * volatile filters (e.g. session-only) call `persist(false)` so the
 * React side knows not to write to the query string.
 */
abstract class Filter
{
    protected string $type;

    protected string $name;

    protected ?string $label = null;

    protected mixed $defaultValue = null;

    protected bool $persist = true;

    protected ?Closure $applyCallback = null;

    protected ?Closure $canSee = null;

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

    public function default(mixed $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    public function persist(bool $persist = true): static
    {
        $this->persist = $persist;

        return $this;
    }

    public function apply(Closure $callback): static
    {
        $this->applyCallback = $callback;

        return $this;
    }

    public function canSee(Closure $callback): static
    {
        $this->canSee = $callback;

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

    public function getDefault(): mixed
    {
        return $this->defaultValue;
    }

    public function isPersisted(): bool
    {
        return $this->persist;
    }

    public function isVisibleFor(?Model $record = null): bool
    {
        if ($this->canSee === null) {
            return true;
        }

        return (bool) ($this->canSee)($record);
    }

    /**
     * Applies the filter to the given query builder.
     *
     * Subclasses provide the default behaviour; a registered
     * `apply()` callback (see `applyResolved()`) overrides it.
     *
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    public function applyToQuery(Builder $query, mixed $value): Builder
    {
        return $this->applyResolved(
            $query,
            $value,
            fn (Builder $q, mixed $v): Builder => $this->applyDefault($q, $v),
        );
    }

    /**
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    protected function applyResolved(Builder $query, mixed $value, Closure $fallback): Builder
    {
        if ($this->applyCallback !== null) {
            $resolved = ($this->applyCallback)($query, $value);

            return $resolved instanceof Builder ? $resolved : $query;
        }

        $resolved = $fallback($query, $value);

        return $resolved instanceof Builder ? $resolved : $query;
    }

    /**
     * Default apply behaviour. Subclasses override this; the
     * abstract base treats unset/empty values as a no-op so a
     * filter that the user did not touch leaves the query alone.
     *
     * @param Builder<Model> $query
     *
     * @return Builder<Model>
     */
    protected function applyDefault(Builder $query, mixed $value): Builder
    {
        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTypeSpecificProps(): array
    {
        return [];
    }

    /**
     * Serialise the filter declaration for the Inertia payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'label' => $this->getLabel(),
            'default' => $this->defaultValue,
            'persist' => $this->persist,
            'props' => $this->getTypeSpecificProps(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Base class for dashboard widgets.
 *
 * Widgets are presentational units rendered in a panel dashboard.
 * Each subclass implements `data()` (the per-render payload) and
 * declares the React `component()` name that consumes it.
 *
 * Common dimensions:
 *   - `heading` / `description` — visual chrome
 *   - `sort` — ordering inside the dashboard column
 *   - `columnSpan` — int (1..12) or string (`'full'`, `'1/2'`, etc.)
 *   - `pollingInterval` — seconds; null means no polling
 *   - `deferred` — when true, server emits `data: null` and the
 *     client fetches lazily via WidgetDataController (WIDGETS-006)
 *   - `canSee` — Closure receiving `?Authenticatable`; false hides
 *
 * Subclasses are expected to be `final` and to declare `protected
 * string $type` (snake_case identifier — `stat`, `chart`, etc.) and
 * `protected string $component` (PascalCase React component name —
 * `StatWidget`, `ChartWidget`, etc.). Apps can register custom types
 * via `WidgetRegistry::register()`.
 */
abstract class Widget
{
    /**
     * The widget type identifier (`stat`, `chart`, `table`, etc.).
     * Subclasses must override.
     */
    protected string $type = '';

    /**
     * The React component name responsible for rendering the
     * widget on the client. Subclasses must override.
     */
    protected string $component = '';

    protected ?string $heading = null;

    protected ?string $description = null;

    protected ?int $sort = null;

    protected int|string $columnSpan = 1;

    protected ?int $pollingInterval = null;

    protected bool $deferred = false;

    protected ?Closure $canSee = null;

    /** @var array<string, mixed> */
    protected array $filters = [];

    public function __construct(
        protected readonly string $name,
    ) {}

    public function heading(string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function sort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function columnSpan(int|string $span): static
    {
        $this->columnSpan = is_int($span) ? max(1, $span) : $span;

        return $this;
    }

    /**
     * Set the polling interval in seconds. The client refetches
     * widget data every N seconds via setInterval. Pass `0` (or
     * negative) to disable.
     */
    public function poll(int $seconds): static
    {
        $this->pollingInterval = $seconds > 0 ? $seconds : null;

        return $this;
    }

    public function deferred(bool $deferred = true): static
    {
        $this->deferred = $deferred;

        return $this;
    }

    public function canSee(Closure $callback): static
    {
        $this->canSee = $callback;

        return $this;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function filters(array $filters): static
    {
        $this->filters = $filters;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Read a filter value previously applied via `filters([...])`,
     * falling back to `$default` when the filter is not set. This
     * is the canonical reader subclasses should call inside their
     * `data()` method (mirrors `Filter::default` resolution on the
     * Dashboard side).
     */
    public function filterValue(string $name, mixed $default = null): mixed
    {
        return $this->filters[$name] ?? $default;
    }

    /** @return array<string, mixed> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSort(): ?int
    {
        return $this->sort;
    }

    public function getColumnSpan(): int|string
    {
        return $this->columnSpan;
    }

    public function getPollingInterval(): ?int
    {
        return $this->pollingInterval;
    }

    public function isDeferred(): bool
    {
        return $this->deferred;
    }

    /**
     * Stable identifier for the widget instance. Defaults to
     * `<type>:<name>` so two `StatWidget`s with different names
     * produce different ids on the client.
     */
    public function id(): string
    {
        return $this->type.':'.$this->name;
    }

    public function canBeSeenBy(?Authenticatable $user): bool
    {
        if ($this->canSee === null) {
            return true;
        }

        return (bool) ($this->canSee)($user);
    }

    /**
     * Per-render data emitted to the React component. Subclasses
     * implement (e.g. `StatWidget` returns
     * `['value' => $count, 'trend' => '+12%']`).
     *
     * Called by `toArray()` only when `deferred` is false. Deferred
     * widgets fetch via the data controller endpoint.
     *
     * @return array<string, mixed>
     */
    abstract public function data(): array;

    /**
     * Serialise the widget for the Inertia payload. Deferred
     * widgets emit `data: null` so the client knows to fetch.
     *
     * @return array<string, mixed>
     */
    public function toArray(?Authenticatable $user = null): array
    {
        return [
            'id' => $this->id(),
            'name' => $this->name,
            'type' => $this->type,
            'component' => $this->component,
            'heading' => $this->heading,
            'description' => $this->description,
            'sort' => $this->sort,
            'columnSpan' => $this->columnSpan,
            'pollingInterval' => $this->pollingInterval,
            'deferred' => $this->deferred,
            'filters' => $this->filters,
            'data' => $this->deferred ? null : $this->data(),
        ];
    }
}

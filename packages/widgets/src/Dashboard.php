<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Arqel\Widgets\Filters\Filter;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Holds the declarative dashboard schema: a list of widgets with
 * shared layout config (column count, polling default, etc.) and an
 * identity (`id`/`label`/`path`) so a panel may host multiple
 * dashboards side-by-side.
 *
 * Widget entries can be either `Widget` instances or class-strings
 * (`class-string<Widget>`); class-strings are resolved through the
 * Laravel container at serialisation time (`resolve()`/`toArray()`).
 *
 * `Resource::widgets()` (or panel-level `widgets([])`) returns a
 * `Dashboard` instance; the controller serialises it for Inertia.
 */
final class Dashboard
{
    /** @var list<Widget|class-string<Widget>> */
    private array $widgets = [];

    /** @var int|array<string, int> */
    private int|array $columns = 12;

    private ?string $heading = null;

    private ?string $description = null;

    /** @var array<string, mixed> */
    private array $filters = [];

    /** @var list<Filter> */
    private array $declaredFilters = [];

    /** @var array<string, mixed> */
    private array $filterDefaults = [];

    private ?Closure $canSee = null;

    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $path = null,
    ) {}

    public static function make(string $id, string $label, ?string $path = null): self
    {
        return new self($id, $label, $path);
    }

    /**
     * Accept a mix of `Widget` instances and `class-string<Widget>`.
     * Resolution to instances is deferred to `resolve()`/`toArray()`,
     * so the registry stays cheap to build at boot time.
     *
     * Non-Widget / non-class-string entries are dropped silently —
     * misconfiguration in user code shouldn't crash the panel.
     *
     * @param array<int, mixed> $widgets
     */
    public function widgets(array $widgets): self
    {
        $valid = [];
        foreach ($widgets as $widget) {
            if ($widget instanceof Widget) {
                $valid[] = $widget;

                continue;
            }

            if (is_string($widget) && is_subclass_of($widget, Widget::class)) {
                $valid[] = $widget;
            }
        }

        $this->widgets = $valid;

        return $this;
    }

    /**
     * @param Widget|class-string<Widget> $widget
     */
    public function addWidget(Widget|string $widget): self
    {
        if (is_string($widget) && ! is_subclass_of($widget, Widget::class)) {
            return $this;
        }

        $this->widgets[] = $widget;

        return $this;
    }

    /**
     * Number of columns in the dashboard grid, either as a flat
     * int (1..12) or a responsive map keyed by breakpoint
     * (e.g. `['sm' => 1, 'md' => 2, 'lg' => 3, 'xl' => 4]`). Each
     * value is clamped to 1..12 and unknown breakpoint keys are
     * dropped silently.
     *
     * @param int|array<string, mixed> $columns
     */
    public function columns(int|array $columns): self
    {
        if (is_int($columns)) {
            $this->columns = max(1, min(12, $columns));

            return $this;
        }

        $allowed = ['sm', 'md', 'lg', 'xl', '2xl'];
        $clean = [];
        foreach ($columns as $breakpoint => $value) {
            if (! is_string($breakpoint) || ! in_array($breakpoint, $allowed, true)) {
                continue;
            }
            if (! is_int($value)) {
                continue;
            }
            $clean[$breakpoint] = max(1, min(12, $value));
        }

        $this->columns = $clean;

        return $this;
    }

    public function heading(string $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Filter declarations rendered by the React side (e.g., date
     * range, dropdown). Two modes are supported:
     *
     * 1. Legacy `array<string, mixed>` — passthrough metadata
     *    propagated as-is to widgets (kept for BC).
     * 2. Declarative `list<Filter>` — each `Filter` instance is
     *    serialised on the React side and its `default` is merged
     *    into every widget's filter map at `resolve()` time.
     *
     * Detection: any element being a `Filter` instance switches
     * the call into declarative mode.
     *
     * @param array<int|string, mixed> $filters
     */
    public function filters(array $filters): self
    {
        $hasFilterInstance = false;
        foreach ($filters as $entry) {
            if ($entry instanceof Filter) {
                $hasFilterInstance = true;

                break;
            }
        }

        if ($hasFilterInstance) {
            $declared = [];
            $defaults = [];
            foreach ($filters as $entry) {
                if (! $entry instanceof Filter) {
                    continue;
                }
                $declared[] = $entry;
                $defaults[$entry->getName()] = $entry->getDefault();
            }

            $this->declaredFilters = $declared;
            $this->filterDefaults = $defaults;
            $this->filters = $defaults;

            return $this;
        }

        // Legacy passthrough mode.
        /** @var array<string, mixed> $filters */
        $this->declaredFilters = [];
        $this->filterDefaults = [];
        $this->filters = $filters;

        return $this;
    }

    public function canSee(Closure $callback): self
    {
        $this->canSee = $callback;

        return $this;
    }

    public function canBeSeenBy(?Authenticatable $user): bool
    {
        if ($this->canSee === null) {
            return true;
        }

        return (bool) ($this->canSee)($user);
    }

    /** @return list<Widget|class-string<Widget>> */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    /** @return int|array<string, int> */
    public function getColumns(): int|array
    {
        return $this->columns;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** @return array<string, mixed> */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /** @return list<Filter> */
    public function getDeclaredFilters(): array
    {
        return $this->declaredFilters;
    }

    /** @return array<string, mixed> */
    public function getFilterDefaults(): array
    {
        return $this->filterDefaults;
    }

    /**
     * Canonical serialiser for the dashboard schema. Resolves
     * class-string widget entries through the container, filters
     * by `canBeSeenBy($user)`, sorts by `getSort()` (null sorts
     * last), and maps each widget to its array payload.
     *
     * @return array<string, mixed>
     */
    public function resolve(?Authenticatable $user = null): array
    {
        $container = Container::getInstance();
        $resolved = [];
        foreach ($this->widgets as $entry) {
            $widget = $entry;
            if (is_string($widget)) {
                try {
                    $widget = $container->make($widget);
                } catch (Throwable) {
                    continue;
                }
            }

            if (! $widget instanceof Widget) {
                continue;
            }

            if (! $widget->canBeSeenBy($user)) {
                continue;
            }

            // Merge dashboard filter values into the widget. Request-time
            // values already set on the widget (e.g. by WidgetDataController)
            // win over the dashboard's declared defaults.
            $merged = array_merge($this->filters, $widget->getFilters());
            if ($merged !== []) {
                $widget->filters($merged);
            }

            $resolved[] = $widget;
        }

        usort(
            $resolved,
            fn (Widget $a, Widget $b): int => ($a->getSort() ?? PHP_INT_MAX) <=> ($b->getSort() ?? PHP_INT_MAX),
        );

        return [
            'id' => $this->id,
            'label' => $this->label,
            'path' => $this->path,
            'widgets' => array_map(
                fn (Widget $widget): array => $widget->toArray($user),
                $resolved,
            ),
            'filters' => $this->filters,
            'columns' => $this->columns,
            'heading' => $this->heading,
            'description' => $this->description,
        ];
    }

    /**
     * Historical alias for `resolve()` — kept for parity with
     * `Widget::toArray()` / `Form::toArray()` / `Table::toArray()`.
     * Returns the same payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(?Authenticatable $user = null): array
    {
        return $this->resolve($user);
    }
}

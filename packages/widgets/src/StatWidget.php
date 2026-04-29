<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Closure;

/**
 * KPI / "big number" widget — the most common dashboard primitive.
 *
 * Renders a `<StatWidget>` React component (shipped by
 * `@arqel/ui/widgets` in WIDGETS-007+). Apps configure via the
 * fluent API — no subclassing required for the common case:
 *
 *   StatWidget::make('total_users')
 *     ->heading('Total Users')
 *     ->value(fn () => User::count())
 *     ->description(fn () => '+12% vs last week')
 *     ->descriptionIcon('trending-up')
 *     ->color('success')
 *     ->chart(fn () => User::selectRaw('COUNT(*) as c')->...)
 *     ->url('/admin/users');
 *
 * For widgets that pull from heavy queries, prefer:
 *
 *   StatWidget::make('revenue')->value(...)->deferred()
 *
 * so the client lazy-fetches the data via `WidgetDataController`.
 *
 * `value()` and the description/chart helpers accept either a
 * scalar (for static values) or a Closure (resolved in `data()`).
 */
final class StatWidget extends Widget
{
    public const string COLOR_PRIMARY = 'primary';

    public const string COLOR_SECONDARY = 'secondary';

    public const string COLOR_SUCCESS = 'success';

    public const string COLOR_WARNING = 'warning';

    public const string COLOR_DANGER = 'danger';

    public const string COLOR_INFO = 'info';

    private const array VALID_COLORS = [
        self::COLOR_PRIMARY,
        self::COLOR_SECONDARY,
        self::COLOR_SUCCESS,
        self::COLOR_WARNING,
        self::COLOR_DANGER,
        self::COLOR_INFO,
    ];

    protected string $type = 'stat';

    protected string $component = 'StatWidget';

    /** @var int|float|string|Closure(): (int|float|string)|null */
    private mixed $value = null;

    /** @var string|Closure(): ?string|null */
    private mixed $statDescription = null;

    private ?string $descriptionIcon = null;

    private string $color = self::COLOR_PRIMARY;

    private ?string $icon = null;

    /** @var array<int, int|float>|Closure(): array<int, int|float>|null */
    private mixed $chart = null;

    private ?string $url = null;

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * The KPI value. Pass a scalar for static numbers, a Closure
     * for queries that should run at `data()` time:
     *
     *   ->value(42)
     *   ->value(fn () => User::count())
     *
     * @param int|float|string|Closure(): (int|float|string) $value
     */
    public function value(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Optional secondary line shown below the big number — usually
     * a comparison ("+12% vs last week"). Accepts string or Closure.
     *
     * @param string|Closure(): ?string|null $description
     */
    public function statDescription(mixed $description): self
    {
        $this->statDescription = $description;

        return $this;
    }

    public function descriptionIcon(string $icon): self
    {
        $this->descriptionIcon = $icon;

        return $this;
    }

    /**
     * Visual variant: primary | secondary | success | warning |
     * danger | info. Unknown values fall back to primary.
     */
    public function color(string $color): self
    {
        $this->color = in_array($color, self::VALID_COLORS, true)
            ? $color
            : self::COLOR_PRIMARY;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Sparkline data — an array of numbers the React side renders
     * as a tiny line chart. Closure resolution happens at `data()`
     * time so deferred widgets can lazy-fetch the points.
     *
     * @param array<int, int|float>|Closure(): array<int, int|float>|null $chart
     */
    public function chart(mixed $chart): self
    {
        $this->chart = $chart;

        return $this;
    }

    /**
     * Click destination. When set, the React component renders the
     * card as a link.
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return [
            'value' => $this->resolveValue(),
            'description' => $this->resolveStatDescription(),
            'descriptionIcon' => $this->descriptionIcon,
            'color' => $this->color,
            'icon' => $this->icon,
            'chart' => $this->resolveChart(),
            'url' => $this->url,
        ];
    }

    /**
     * @return int|float|string|null
     */
    private function resolveValue(): mixed
    {
        if ($this->value instanceof Closure) {
            $resolved = ($this->value)();

            return is_int($resolved) || is_float($resolved) || is_string($resolved)
                ? $resolved
                : null;
        }

        return $this->value;
    }

    private function resolveStatDescription(): ?string
    {
        if ($this->statDescription instanceof Closure) {
            $resolved = ($this->statDescription)();

            return is_string($resolved) ? $resolved : null;
        }

        return is_string($this->statDescription) ? $this->statDescription : null;
    }

    /**
     * @return array<int, int|float>|null
     */
    private function resolveChart(): ?array
    {
        $resolved = $this->chart instanceof Closure ? ($this->chart)() : $this->chart;

        if (! is_array($resolved)) {
            return null;
        }

        $clean = [];
        foreach ($resolved as $point) {
            if (is_int($point) || is_float($point)) {
                $clean[] = $point;
            }
        }

        return $clean;
    }
}

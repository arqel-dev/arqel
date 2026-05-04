<?php

declare(strict_types=1);

namespace Arqel\Widgets;

use Closure;

/**
 * Chart widget — wraps a Recharts visualisation in the dashboard.
 *
 * The PHP side only serialises chart configuration + data; the
 * actual rendering happens client-side in `@arqel-dev/ui` via Recharts.
 *
 * Expected `chartData` shape:
 *   [
 *     'labels' => ['Jan', 'Feb', ...],
 *     'datasets' => [
 *       ['label' => 'Sales', 'data' => [1, 2, 3], 'color' => '#f00'],
 *       ...
 *     ],
 *   ]
 *
 * Closures are resolved at `data()` time so callers can defer heavy
 * computation behind `deferred(true)` or polling.
 */
final class ChartWidget extends Widget
{
    public const CHART_LINE = 'line';

    public const CHART_BAR = 'bar';

    public const CHART_AREA = 'area';

    public const CHART_PIE = 'pie';

    public const CHART_DONUT = 'donut';

    public const CHART_RADAR = 'radar';

    private const ALLOWED_CHART_TYPES = [
        self::CHART_LINE,
        self::CHART_BAR,
        self::CHART_AREA,
        self::CHART_PIE,
        self::CHART_DONUT,
        self::CHART_RADAR,
    ];

    protected string $type = 'chart';

    protected string $component = 'ChartWidget';

    protected string $chartType = self::CHART_LINE;

    protected int $height = 300;

    protected bool $showLegend = true;

    protected bool $showGrid = true;

    /** @var array<string, mixed>|Closure */
    protected array|Closure $chartData = ['labels' => [], 'datasets' => []];

    /** @var array<string, mixed>|Closure */
    protected array|Closure $chartOptions = [];

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function chartType(string $type): static
    {
        $this->chartType = in_array($type, self::ALLOWED_CHART_TYPES, true)
            ? $type
            : self::CHART_LINE;

        return $this;
    }

    public function height(int $pixels): static
    {
        $this->height = max(50, $pixels);

        return $this;
    }

    public function showLegend(bool $show = true): static
    {
        $this->showLegend = $show;

        return $this;
    }

    public function showGrid(bool $show = true): static
    {
        $this->showGrid = $show;

        return $this;
    }

    /**
     * @param array<string, mixed>|Closure $data
     */
    public function chartData(array|Closure $data): static
    {
        $this->chartData = $data;

        return $this;
    }

    /**
     * @param array<string, mixed>|Closure $options
     */
    public function chartOptions(array|Closure $options): static
    {
        $this->chartOptions = $options;

        return $this;
    }

    public function getChartType(): string
    {
        return $this->chartType;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function isLegendVisible(): bool
    {
        return $this->showLegend;
    }

    public function isGridVisible(): bool
    {
        return $this->showGrid;
    }

    public function data(): array
    {
        return [
            'chartType' => $this->chartType,
            'chartData' => $this->resolveChartData(),
            'chartOptions' => $this->resolveChartOptions(),
            'height' => $this->height,
            'showLegend' => $this->showLegend,
            'showGrid' => $this->showGrid,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveChartData(): array
    {
        if ($this->chartData instanceof Closure) {
            $value = ($this->chartData)();

            if (! is_array($value)) {
                return ['labels' => [], 'datasets' => []];
            }

            $resolved = [];
            foreach ($value as $key => $entry) {
                $resolved[(string) $key] = $entry;
            }

            return $resolved;
        }

        return $this->chartData;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveChartOptions(): array
    {
        if ($this->chartOptions instanceof Closure) {
            $value = ($this->chartOptions)();

            if (! is_array($value)) {
                return [];
            }

            $resolved = [];
            foreach ($value as $key => $entry) {
                $resolved[(string) $key] = $entry;
            }

            return $resolved;
        }

        return $this->chartOptions;
    }
}

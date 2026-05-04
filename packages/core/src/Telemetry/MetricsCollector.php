<?php

declare(strict_types=1);

namespace Arqel\Core\Telemetry;

/**
 * Request-scoped metrics collector.
 *
 * Format-agnostic — stores counters/gauges/histograms keyed by
 * `name + sorted-labels` and exposes a `snapshot()` for exporters.
 *
 * Designed to be bound as a `singleton` (or `scoped` in Laravel 11+)
 * so the same instance is shared across the request, then reset
 * between requests by the consumer (CLI commands, queues) when
 * needed via {@see clear()}.
 */
final class MetricsCollector
{
    /** @var array<string, array{name: string, labels: array<string, string>, value: float}> */
    private array $counters = [];

    /** @var array<string, array{name: string, labels: array<string, string>, value: float}> */
    private array $gauges = [];

    /** @var array<string, array{name: string, labels: array<string, string>, values: list<float>}> */
    private array $histograms = [];

    /**
     * Increment (or create) a counter.
     *
     * @param  array<string, scalar|null>  $labels
     */
    public function counter(string $name, float $value = 1.0, array $labels = []): void
    {
        $normalized = $this->normalizeLabels($labels);
        $key = $this->buildKey($name, $normalized);

        if (! isset($this->counters[$key])) {
            $this->counters[$key] = [
                'name' => $name,
                'labels' => $normalized,
                'value' => 0.0,
            ];
        }

        $this->counters[$key]['value'] += $value;
    }

    /**
     * Set (or create) a gauge to the given value.
     *
     * @param  array<string, scalar|null>  $labels
     */
    public function gauge(string $name, float $value, array $labels = []): void
    {
        $normalized = $this->normalizeLabels($labels);
        $key = $this->buildKey($name, $normalized);

        $this->gauges[$key] = [
            'name' => $name,
            'labels' => $normalized,
            'value' => $value,
        ];
    }

    /**
     * Append an observation to a histogram.
     *
     * @param  array<string, scalar|null>  $labels
     */
    public function histogram(string $name, float $value, array $labels = []): void
    {
        $normalized = $this->normalizeLabels($labels);
        $key = $this->buildKey($name, $normalized);

        if (! isset($this->histograms[$key])) {
            $this->histograms[$key] = [
                'name' => $name,
                'labels' => $normalized,
                'values' => [],
            ];
        }

        $this->histograms[$key]['values'][] = $value;
    }

    /**
     * Snapshot the current state. Each top-level key is a list of
     * homogeneous metric records.
     *
     * @return array{
     *     counters: list<array{name: string, labels: array<string, string>, value: float}>,
     *     gauges: list<array{name: string, labels: array<string, string>, value: float}>,
     *     histograms: list<array{name: string, labels: array<string, string>, values: list<float>, count: int, sum: float}>,
     * }
     */
    public function snapshot(): array
    {
        $histograms = [];
        foreach ($this->histograms as $entry) {
            $values = $entry['values'];
            $histograms[] = [
                'name' => $entry['name'],
                'labels' => $entry['labels'],
                'values' => $values,
                'count' => count($values),
                'sum' => array_sum($values),
            ];
        }

        return [
            'counters' => array_values($this->counters),
            'gauges' => array_values($this->gauges),
            'histograms' => $histograms,
        ];
    }

    /**
     * Reset all stored metrics. Intended for between-request resets
     * in long-lived processes (octane, queue workers).
     */
    public function clear(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
    }

    /**
     * @param  array<string, scalar|null>  $labels
     * @return array<string, string>
     */
    private function normalizeLabels(array $labels): array
    {
        $normalized = [];
        foreach ($labels as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            if ($value === null) {
                $normalized[$key] = '';

                continue;
            }
            if (is_bool($value)) {
                $normalized[$key] = $value ? 'true' : 'false';

                continue;
            }
            $normalized[$key] = (string) $value;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function buildKey(string $name, array $labels): string
    {
        $parts = [];
        foreach ($labels as $k => $v) {
            $parts[] = $k.'='.$v;
        }

        return $name.'|'.implode(',', $parts);
    }
}

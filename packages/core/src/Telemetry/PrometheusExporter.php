<?php

declare(strict_types=1);

namespace Arqel\Core\Telemetry;

/**
 * Serializes a {@see MetricsCollector} snapshot to the Prometheus
 * text exposition format (version 0.0.4).
 *
 * The format is intentionally minimal:
 *   # HELP <name> Arqel-collected metric.
 *   # TYPE <name> counter|gauge|histogram
 *   <name>{label="value",...} <number>
 *
 * Names are sanitized to `[a-z0-9_]` and label values are escaped
 * per the spec (`\\`, `\n`, `"`).
 */
final readonly class PrometheusExporter
{
    public function __construct(private MetricsCollector $collector) {}

    public function export(): string
    {
        $snapshot = $this->collector->snapshot();
        $lines = [];

        foreach ($snapshot['counters'] as $entry) {
            $name = $this->sanitize($entry['name']);
            $lines[] = '# HELP '.$name.' Arqel counter metric.';
            $lines[] = '# TYPE '.$name.' counter';
            $lines[] = $name.$this->formatLabels($entry['labels']).' '.$this->formatValue($entry['value']);
        }

        foreach ($snapshot['gauges'] as $entry) {
            $name = $this->sanitize($entry['name']);
            $lines[] = '# HELP '.$name.' Arqel gauge metric.';
            $lines[] = '# TYPE '.$name.' gauge';
            $lines[] = $name.$this->formatLabels($entry['labels']).' '.$this->formatValue($entry['value']);
        }

        foreach ($snapshot['histograms'] as $entry) {
            $name = $this->sanitize($entry['name']);
            $lines[] = '# HELP '.$name.' Arqel histogram metric.';
            $lines[] = '# TYPE '.$name.' histogram';
            $labels = $this->formatLabels($entry['labels']);
            $lines[] = $name.'_count'.$labels.' '.$entry['count'];
            $lines[] = $name.'_sum'.$labels.' '.$this->formatValue($entry['sum']);
        }

        return implode("\n", $lines).(count($lines) > 0 ? "\n" : '');
    }

    private function sanitize(string $name): string
    {
        $lower = strtolower($name);
        $replaced = (string) preg_replace('/[^a-z0-9_]/', '_', $lower);

        // Prometheus requires names to start with [a-zA-Z_:].
        if ($replaced === '' || ! preg_match('/^[a-z_]/', $replaced)) {
            $replaced = '_'.$replaced;
        }

        return $replaced;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function formatLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $parts = [];
        foreach ($labels as $key => $value) {
            $cleanKey = $this->sanitize($key);
            $escaped = $this->escapeLabelValue($value);
            $parts[] = $cleanKey.'="'.$escaped.'"';
        }

        return '{'.implode(',', $parts).'}';
    }

    private function escapeLabelValue(string $value): string
    {
        return str_replace(
            ['\\', "\n", '"'],
            ['\\\\', '\\n', '\\"'],
            $value,
        );
    }

    private function formatValue(float $value): string
    {
        if (is_nan($value)) {
            return 'NaN';
        }
        if (is_infinite($value)) {
            return $value > 0 ? '+Inf' : '-Inf';
        }
        // Strip trailing zeroes for integer-valued floats.
        if (floor($value) === $value && abs($value) < 1.0e15) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }
}

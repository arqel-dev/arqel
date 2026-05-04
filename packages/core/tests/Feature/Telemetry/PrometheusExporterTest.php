<?php

declare(strict_types=1);

use Arqel\Core\Telemetry\MetricsCollector;
use Arqel\Core\Telemetry\PrometheusExporter;

it('returns empty string when snapshot is empty', function (): void {
    $exporter = new PrometheusExporter(new MetricsCollector);
    expect($exporter->export())->toBe('');
});

it('emits HELP, TYPE and value lines for counters', function (): void {
    $collector = new MetricsCollector;
    $collector->counter('arqel_resource_views_total', 3.0, ['resource' => 'users']);
    $exporter = new PrometheusExporter($collector);

    $output = $exporter->export();

    expect($output)->toContain('# HELP arqel_resource_views_total');
    expect($output)->toContain('# TYPE arqel_resource_views_total counter');
    expect($output)->toContain('arqel_resource_views_total{resource="users"} 3');
});

it('emits histogram count + sum lines', function (): void {
    $collector = new MetricsCollector;
    $collector->histogram('arqel_action_duration_ms', 10.0, ['action' => 'save']);
    $collector->histogram('arqel_action_duration_ms', 30.0, ['action' => 'save']);
    $exporter = new PrometheusExporter($collector);

    $output = $exporter->export();

    expect($output)->toContain('# TYPE arqel_action_duration_ms histogram');
    expect($output)->toContain('arqel_action_duration_ms_count{action="save"} 2');
    expect($output)->toContain('arqel_action_duration_ms_sum{action="save"} 40');
});

it('escapes backslashes, newlines and quotes in label values', function (): void {
    $collector = new MetricsCollector;
    $collector->counter('arqel_weird', 1.0, ['msg' => "a\"b\\c\nd"]);
    $exporter = new PrometheusExporter($collector);

    $output = $exporter->export();

    expect($output)->toContain('msg="a\\"b\\\\c\\nd"');
});

it('sanitizes metric and label names to a-z0-9_', function (): void {
    $collector = new MetricsCollector;
    $collector->counter('Arqel.Foo-Bar', 1.0, ['Some-Label' => 'x']);
    $exporter = new PrometheusExporter($collector);

    $output = $exporter->export();

    expect($output)->toContain('arqel_foo_bar');
    expect($output)->toContain('some_label="x"');
});

it('serializes multiple metric kinds in one export', function (): void {
    $collector = new MetricsCollector;
    $collector->counter('arqel_a_total', 1.0);
    $collector->gauge('arqel_b', 5.5);
    $collector->histogram('arqel_c_ms', 1.0);
    $exporter = new PrometheusExporter($collector);

    $output = $exporter->export();

    expect($output)->toContain('# TYPE arqel_a_total counter');
    expect($output)->toContain('# TYPE arqel_b gauge');
    expect($output)->toContain('# TYPE arqel_c_ms histogram');
});

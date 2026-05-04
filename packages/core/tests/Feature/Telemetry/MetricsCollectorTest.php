<?php

declare(strict_types=1);

use Arqel\Core\Telemetry\MetricsCollector;

it('increments counter values across calls', function (): void {
    $collector = new MetricsCollector;
    $collector->counter('arqel_resource_views_total', 1.0, ['resource' => 'users']);
    $collector->counter('arqel_resource_views_total', 2.0, ['resource' => 'users']);

    $snapshot = $collector->snapshot();

    expect($snapshot['counters'])->toHaveCount(1);
    expect($snapshot['counters'][0]['value'])->toBe(3.0);
    expect($snapshot['counters'][0]['labels'])->toBe(['resource' => 'users']);
});

it('keeps counters with different labels separate', function (): void {
    $collector = new MetricsCollector;
    $collector->counter('arqel_resource_views_total', 1.0, ['resource' => 'users']);
    $collector->counter('arqel_resource_views_total', 1.0, ['resource' => 'posts']);

    $snapshot = $collector->snapshot();

    expect($snapshot['counters'])->toHaveCount(2);
});

it('overwrites gauges with the latest value', function (): void {
    $collector = new MetricsCollector;
    $collector->gauge('arqel_active_users', 5.0);
    $collector->gauge('arqel_active_users', 8.0);

    $snapshot = $collector->snapshot();

    expect($snapshot['gauges'])->toHaveCount(1);
    expect($snapshot['gauges'][0]['value'])->toBe(8.0);
});

it('appends histogram observations and computes count + sum', function (): void {
    $collector = new MetricsCollector;
    $collector->histogram('arqel_action_duration_ms', 10.0, ['action' => 'delete']);
    $collector->histogram('arqel_action_duration_ms', 20.0, ['action' => 'delete']);
    $collector->histogram('arqel_action_duration_ms', 30.0, ['action' => 'delete']);

    $snapshot = $collector->snapshot();

    expect($snapshot['histograms'])->toHaveCount(1);
    expect($snapshot['histograms'][0]['count'])->toBe(3);
    expect($snapshot['histograms'][0]['sum'])->toBe(60.0);
});

it('clears all metrics on demand', function (): void {
    $collector = new MetricsCollector;
    $collector->counter('a');
    $collector->gauge('b', 1.0);
    $collector->histogram('c', 1.0);

    $collector->clear();

    $snapshot = $collector->snapshot();
    expect($snapshot['counters'])->toBe([]);
    expect($snapshot['gauges'])->toBe([]);
    expect($snapshot['histograms'])->toBe([]);
});

it('normalizes scalar label values to strings and sorts labels', function (): void {
    $collector = new MetricsCollector;
    $collector->counter('arqel_test', 1.0, ['z' => 1, 'a' => true, 'm' => null]);

    $snapshot = $collector->snapshot();

    expect($snapshot['counters'][0]['labels'])->toBe([
        'a' => 'true',
        'm' => '',
        'z' => '1',
    ]);
});

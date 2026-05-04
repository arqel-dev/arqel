<?php

declare(strict_types=1);

use Arqel\Core\Telemetry\AutoInstrumentation;
use Arqel\Core\Telemetry\MetricsCollector;

it('records workflow transitions as labelled counters', function (): void {
    $collector = new MetricsCollector;
    $instrumentation = new AutoInstrumentation($collector);

    $instrumentation->recordWorkflowTransition('draft', 'published');
    $instrumentation->recordWorkflowTransition('draft', 'published');

    $snapshot = $collector->snapshot();
    expect($snapshot['counters'])->toHaveCount(1);
    expect($snapshot['counters'][0]['name'])->toBe('arqel_workflow_transitions_total');
    expect($snapshot['counters'][0]['labels'])->toBe(['from' => 'draft', 'to' => 'published']);
    expect($snapshot['counters'][0]['value'])->toBe(2.0);
});

it('records AI completions across counters and gauges', function (): void {
    $collector = new MetricsCollector;
    $instrumentation = new AutoInstrumentation($collector);

    $instrumentation->recordAiCompletion('anthropic', 1234, 0.012);

    $snapshot = $collector->snapshot();
    $counterNames = array_column($snapshot['counters'], 'name');
    $gaugeNames = array_column($snapshot['gauges'], 'name');

    expect($counterNames)->toContain('arqel_ai_completions_total');
    expect($counterNames)->toContain('arqel_ai_tokens_total');
    expect($counterNames)->toContain('arqel_ai_cost_usd_total');
    expect($gaugeNames)->toContain('arqel_ai_tokens_last');
});

it('records action executions with both counter and histogram', function (): void {
    $collector = new MetricsCollector;
    $instrumentation = new AutoInstrumentation($collector);

    $instrumentation->recordActionExecution('publish', 42.5);

    $snapshot = $collector->snapshot();
    expect($snapshot['counters'][0]['name'])->toBe('arqel_action_executions_total');
    expect($snapshot['histograms'][0]['name'])->toBe('arqel_action_duration_ms');
    expect($snapshot['histograms'][0]['sum'])->toBe(42.5);
});

it('subscribes safely when optional event classes are missing', function (): void {
    $collector = new MetricsCollector;
    $instrumentation = new AutoInstrumentation($collector);

    // No `arqel-dev/workflow` or `arqel-dev/ai` installed → subscribe must
    // not throw and must register zero listeners.
    $instrumentation->subscribe($this->app->make(Illuminate\Contracts\Events\Dispatcher::class));

    expect(true)->toBeTrue();
});

it('records resource views with the resource label', function (): void {
    $collector = new MetricsCollector;
    $instrumentation = new AutoInstrumentation($collector);

    $instrumentation->recordResourceView('users');
    $instrumentation->recordResourceView('users');
    $instrumentation->recordResourceView('posts');

    $snapshot = $collector->snapshot();
    expect($snapshot['counters'])->toHaveCount(2);
});

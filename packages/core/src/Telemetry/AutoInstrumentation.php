<?php

declare(strict_types=1);

namespace Arqel\Core\Telemetry;

use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Records Arqel-specific metrics through {@see MetricsCollector}
 * via convenience methods + automatic event listeners.
 *
 * Cross-package events (`arqel/workflow`, `arqel/ai`) are wired
 * defensively with `class_exists` so this works standalone when
 * those packages are not installed.
 */
final readonly class AutoInstrumentation
{
    public function __construct(private MetricsCollector $collector) {}

    public function recordResourceView(string $resourceSlug): void
    {
        $this->collector->counter(
            'arqel_resource_views_total',
            1.0,
            ['resource' => $resourceSlug],
        );
    }

    public function recordActionExecution(string $actionName, float $durationMs): void
    {
        $this->collector->counter(
            'arqel_action_executions_total',
            1.0,
            ['action' => $actionName],
        );
        $this->collector->histogram(
            'arqel_action_duration_ms',
            $durationMs,
            ['action' => $actionName],
        );
    }

    public function recordAiCompletion(string $provider, int $tokens, float $costUsd): void
    {
        $this->collector->counter(
            'arqel_ai_completions_total',
            1.0,
            ['provider' => $provider],
        );
        $this->collector->gauge(
            'arqel_ai_tokens_last',
            (float) $tokens,
            ['provider' => $provider],
        );
        $this->collector->counter(
            'arqel_ai_tokens_total',
            (float) $tokens,
            ['provider' => $provider],
        );
        $this->collector->counter(
            'arqel_ai_cost_usd_total',
            $costUsd,
            ['provider' => $provider],
        );
    }

    public function recordWorkflowTransition(string $from, string $to): void
    {
        $this->collector->counter(
            'arqel_workflow_transitions_total',
            1.0,
            ['from' => $from, 'to' => $to],
        );
    }

    /**
     * Subscribe defensively to optional cross-package events. Safe
     * to call when `arqel/workflow` or `arqel/ai` are missing — the
     * `class_exists` guards short-circuit before the listener is
     * registered.
     */
    public function subscribe(Dispatcher $events): void
    {
        $workflowEvent = 'Arqel\\Workflow\\Events\\StateTransitioned';
        if (class_exists($workflowEvent)) {
            $events->listen($workflowEvent, function (object $event): void {
                try {
                    $from = isset($event->from) && is_scalar($event->from) ? (string) $event->from : '';
                    $to = isset($event->to) && is_scalar($event->to) ? (string) $event->to : '';
                    $this->recordWorkflowTransition($from, $to);
                } catch (Throwable) {
                    // Telemetry must never break business logic.
                }
            });
        }

        $aiEvent = 'Arqel\\Ai\\Events\\AiCompletionGenerated';
        if (class_exists($aiEvent)) {
            $events->listen($aiEvent, function (object $event): void {
                try {
                    $provider = isset($event->provider) && is_scalar($event->provider) ? (string) $event->provider : '';
                    $tokens = isset($event->tokens) && is_numeric($event->tokens) ? (int) $event->tokens : 0;
                    $costUsd = isset($event->costUsd) && is_numeric($event->costUsd) ? (float) $event->costUsd : 0.0;
                    $this->recordAiCompletion($provider, $tokens, $costUsd);
                } catch (Throwable) {
                    // Telemetry must never break business logic.
                }
            });
        }
    }
}

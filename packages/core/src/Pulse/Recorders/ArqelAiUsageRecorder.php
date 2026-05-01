<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse\Recorders;

use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Pulse recorder: AI tokens consumed per provider (LCLOUD-003).
 *
 * Subscribes to `Arqel\Ai\Events\AiCompletionGenerated` (when the
 * `arqel/ai` package is installed) and aggregates the
 * `total_tokens` field per provider into Pulse.
 */
final class ArqelAiUsageRecorder
{
    private const string EVENT_CLASS = 'Arqel\\Ai\\Events\\AiCompletionGenerated';

    private const string PULSE_KEY = 'arqel_ai_tokens';

    public function subscribe(Dispatcher $events): void
    {
        if (! class_exists(self::EVENT_CLASS)) {
            return;
        }

        $events->listen(self::EVENT_CLASS, [self::class, 'handle']);
    }

    public function handle(object $event): void
    {
        try {
            if (! class_exists(\Laravel\Pulse\Facades\Pulse::class)) {
                return;
            }

            [$provider, $tokens] = $this->extractUsage($event);
            if ($provider === null || $tokens <= 0) {
                return;
            }

            \Laravel\Pulse\Facades\Pulse::record(self::PULSE_KEY, $provider, $tokens)->sum();
        } catch (Throwable) {
            // never let metrics break event flow
        }
    }

    /**
     * @return array{0: string|null, 1: int}
     */
    private function extractUsage(object $event): array
    {
        $provider = null;
        if (property_exists($event, 'provider') && is_string($event->provider)) {
            $provider = $event->provider;
        } elseif (property_exists($event, 'providerName') && is_string($event->providerName)) {
            $provider = $event->providerName;
        }

        $tokens = 0;
        if (property_exists($event, 'totalTokens') && is_int($event->totalTokens)) {
            $tokens = $event->totalTokens;
        } elseif (property_exists($event, 'tokens') && is_int($event->tokens)) {
            $tokens = $event->tokens;
        }

        return [$provider, $tokens];
    }
}

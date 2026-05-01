<?php

declare(strict_types=1);

namespace Arqel\Core\Pulse\Recorders;

use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Pulse recorder: counts Arqel action executions (LCLOUD-003).
 *
 * Subscribes to `Arqel\Actions\Events\ActionExecuted` (when the
 * `arqel/actions` package is installed) and forwards each event to
 * Pulse via `Pulse::record('arqel_action', $name)->count()`.
 *
 * The class is loaded unconditionally by `arqel/core`, but every
 * branch is defensive: no Pulse, no `arqel/actions`, or a malformed
 * event all degrade to a silent no-op.
 */
final class ArqelActionRecorder
{
    private const string EVENT_CLASS = 'Arqel\\Actions\\Events\\ActionExecuted';

    private const string PULSE_KEY = 'arqel_action';

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

            $name = $this->extractActionName($event);
            if ($name === null) {
                return;
            }

            \Laravel\Pulse\Facades\Pulse::record(self::PULSE_KEY, $name)->count();
        } catch (Throwable) {
            // never let metrics break event flow
        }
    }

    private function extractActionName(object $event): ?string
    {
        // Best-effort: support both `actionName` and `action`
        // properties without binding to a concrete class.
        if (property_exists($event, 'actionName') && is_string($event->actionName)) {
            return $event->actionName;
        }
        if (property_exists($event, 'action') && is_string($event->action)) {
            return $event->action;
        }

        return null;
    }
}

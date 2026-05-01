<?php

declare(strict_types=1);

namespace Arqel\Realtime\Workflow;

use Arqel\Realtime\Events\ResourceUpdated;

/**
 * Listener default que liga `Arqel\Workflow\Events\StateTransitioned` ao
 * pipeline de broadcasting do `arqel/realtime`.
 *
 * **Cross-package defensive design**: o listener fica em `arqel/realtime`
 * (não em `arqel/workflow`) porque a direção da dependência é `realtime →
 * workflow` — `realtime` sabe da existência opcional de `workflow`, mas
 * `workflow` ignora `realtime`. O type-hint do `$event` é `mixed` e a
 * checagem é feita via `instanceof` por FQCN string para que o pacote
 * boote mesmo sem `arqel/workflow` instalado em runtime.
 *
 * Quando ativo, dispara `Arqel\Realtime\Events\ResourceUpdated` — que
 * já implementa `ShouldBroadcast` — usando o `record` carregado pelo
 * evento de workflow. Os canais `arqel.{slug}` e `arqel.{slug}.{id}`
 * derivados pelo próprio `ResourceUpdated::broadcastOn()` carregam a
 * notificação para a UI.
 *
 * Kill switch: `arqel-realtime.workflow.broadcast_state_transitions`.
 */
final readonly class BroadcastStateTransitionListener
{
    public function __construct() {}

    /**
     * Aceita `mixed` para evitar hard-dep no type-hint — se a class do
     * evento de workflow não estiver carregada, o listener apenas
     * retorna sem disparar nada.
     */
    public function handle(mixed $event): void
    {
        if (! $this->shouldBroadcast()) {
            return;
        }

        $eventClass = '\\Arqel\\Workflow\\Events\\StateTransitioned';

        if (! class_exists($eventClass)) {
            return;
        }

        if (! ($event instanceof $eventClass)) {
            return;
        }

        /** @var \Arqel\Workflow\Events\StateTransitioned $event */
        $record = $event->record;

        ResourceUpdated::dispatch(
            $record::class,
            $record,
            $event->userId,
        );
    }

    private function shouldBroadcast(): bool
    {
        $flag = function_exists('config')
            ? config('arqel-realtime.workflow.broadcast_state_transitions')
            : null;

        return $flag === null || (bool) $flag;
    }
}

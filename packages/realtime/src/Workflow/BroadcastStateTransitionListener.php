<?php

declare(strict_types=1);

namespace Arqel\Realtime\Workflow;

use Arqel\Realtime\Events\ResourceUpdated;
use Arqel\Workflow\Events\StateTransitioned;

/**
 * Listener default que liga `Arqel\Workflow\Events\StateTransitioned` ao
 * pipeline de broadcasting do `arqel-dev/realtime`.
 *
 * **Cross-package defensive design**: o listener fica em `arqel-dev/realtime`
 * (não em `arqel-dev/workflow`) porque a direção da dependência é `realtime →
 * workflow` — `realtime` sabe da existência opcional de `workflow`, mas
 * `workflow` ignora `realtime`. O type-hint do `$event` é `mixed` e a
 * checagem é feita via `instanceof` por FQCN string para que o pacote
 * boote mesmo sem `arqel-dev/workflow` instalado em runtime.
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

        /** @var StateTransitioned $event */
        $record = $event->record;

        ResourceUpdated::dispatch(
            $this->resolveResourceClass($record::class),
            $record,
            $event->userId,
        );
    }

    /**
     * Resolve the registered Resource class for the given model so the
     * broadcast lands on the Resource slug channel (`arqel.{slug}`) instead
     * of the `class_basename` fallback `ResourceUpdated::resolveSlug()` uses
     * when handed a bare model FQCN.
     *
     * Falls back to the model class when the `arqel-dev/core` registry is
     * unbound (standalone realtime) or has no Resource for the model — the
     * basename slug is then the only thing available, preserving the prior
     * behaviour for those setups.
     *
     * @param class-string $modelClass
     *
     * @return class-string
     */
    private function resolveResourceClass(string $modelClass): string
    {
        $registryClass = 'Arqel\\Core\\Resources\\ResourceRegistry';

        if (! app()->bound($registryClass)) {
            return $modelClass;
        }

        $registry = app($registryClass);

        if (! method_exists($registry, 'findByModel')) {
            return $modelClass;
        }

        /** @var class-string|null $resourceClass */
        $resourceClass = $registry->findByModel($modelClass);

        return $resourceClass ?? $modelClass;
    }

    private function shouldBroadcast(): bool
    {
        $flag = function_exists('config')
            ? config('arqel-realtime.workflow.broadcast_state_transitions')
            : null;

        return $flag === null || (bool) $flag;
    }
}

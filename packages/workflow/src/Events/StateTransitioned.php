<?php

declare(strict_types=1);

namespace Arqel\Workflow\Events;

use Arqel\Workflow\Concerns\HasWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento Laravel disparado *após* uma transição de state ter ocorrido em um
 * model que usa o trait `Arqel\Workflow\Concerns\HasWorkflow`.
 *
 * Carrega o snapshot mínimo necessário para listeners user-land (audit log,
 * notificações, broadcast em tempo real, métricas). Permanece **desacoplado**
 * de `ShouldBroadcast` por design: broadcasting em tempo real é opt-in via
 * listener dedicado em user-land (ou no pacote `arqel-dev/realtime`), mantendo
 * `arqel-dev/workflow` standalone — alinhado com o duck-typing do trait.
 *
 * @see HasWorkflow::transitionTo()
 */
final class StateTransitioned
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<string, mixed> $context Payload arbitrário propagado
     *                                      do call-site da transição.
     */
    public function __construct(
        public readonly Model $record,
        public readonly string $from,
        public readonly string $to,
        public readonly ?int $userId = null,
        public readonly array $context = [],
    ) {}
}

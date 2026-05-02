<?php

declare(strict_types=1);

namespace Arqel\Realtime\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento broadcastado a cada snapshot Yjs persistido.
 *
 * Channel: `arqel.collab.{modelType}.{modelId}.{field}` (PrivateChannel,
 * autorizado pelo `AwarenessChannelAuthorizer`).
 *
 * O payload `state` é o blob binário Yjs codificado em base64. Versão
 * monotónica conforme `YjsDocument::$version` para que clientes possam
 * descartar updates obsoletos durante reconnects.
 */
final class YjsUpdateReceived implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $modelType,
        public readonly mixed $modelId,
        public readonly string $field,
        public readonly string $stateBase64,
        public readonly int $version,
        public readonly ?int $userId,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("arqel.collab.{$this->modelType}.{$this->modelId}.{$this->field}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'collab.update';
    }

    /**
     * @return array{state: string, version: int, by_user_id: int|null}
     */
    public function broadcastWith(): array
    {
        return [
            'state' => $this->stateBase64,
            'version' => $this->version,
            'by_user_id' => $this->userId,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Ai\Events;

use Arqel\Ai\AiCompletionResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado pelo `AiManager` após cada chamada bem-sucedida a um provider.
 * Permite que consumidores escutem para métricas, logs custom, ou
 * extension-hooks (e.g. write-through cache em outro storage).
 */
final class AiCompletionGenerated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AiCompletionResult $result,
        public readonly string $providerName,
        public readonly ?int $userId = null,
    ) {}
}

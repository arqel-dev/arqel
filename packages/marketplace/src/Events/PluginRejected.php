<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Events;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando um Plugin é rejeitado por admin (MKTPLC-002).
 */
final class PluginRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Plugin $plugin,
        public readonly string $reason,
    ) {}
}

<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Events;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando um pagamento de plugin premium é confirmado (MKTPLC-004-checkout).
 */
final class PluginPurchased
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Plugin $plugin,
        public readonly PluginPurchase $purchase,
    ) {}
}

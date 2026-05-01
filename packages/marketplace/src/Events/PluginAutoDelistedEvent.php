<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Events;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\SecurityScan;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando um plugin é auto-delistado por finding crítico (MKTPLC-009).
 */
final class PluginAutoDelistedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Plugin $plugin,
        public readonly SecurityScan $scan,
    ) {}
}

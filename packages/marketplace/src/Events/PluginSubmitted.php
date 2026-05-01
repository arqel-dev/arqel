<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Events;

use Arqel\Marketplace\Models\Plugin;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando um Plugin é submetido para review (MKTPLC-002).
 */
final class PluginSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Plugin $plugin,
    ) {}
}

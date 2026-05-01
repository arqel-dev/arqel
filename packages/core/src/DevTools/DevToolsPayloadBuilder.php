<?php

declare(strict_types=1);

namespace Arqel\Core\DevTools;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Builds the `__devtools` shared prop payload (DEVTOOLS-004).
 *
 * The shape is **convention-reserved**: only the official Arqel
 * runtime should populate `__devtools` on Inertia pageProps. Apps
 * MUST NOT emit a custom `__devtools` key — the React hook treats it
 * as authoritative and will overwrite mismatching shapes.
 *
 * Production safety: returns `null` whenever
 * `app()->environment('local')` is false. Stack traces and Gate
 * arguments would otherwise leak through Inertia responses.
 */
final readonly class DevToolsPayloadBuilder
{
    public function __construct(
        private Application $app,
        private PolicyLogCollector $collector,
    ) {}

    /**
     * @return array{policyLog: list<array<string, mixed>>, queryCount: int, memoryUsage: int}|null
     */
    public function build(): ?array
    {
        if (! $this->app->environment('local')) {
            return null;
        }

        return [
            'policyLog' => $this->collector->all(),
            'queryCount' => $this->resolveQueryCount(),
            'memoryUsage' => memory_get_peak_usage(true),
        ];
    }

    private function resolveQueryCount(): int
    {
        try {
            $log = DB::getQueryLog();

            return is_array($log) ? count($log) : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}

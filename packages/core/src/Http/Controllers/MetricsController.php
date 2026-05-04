<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\Telemetry\PrometheusExporter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * `GET /admin/_metrics` — Prometheus text exposition endpoint.
 *
 * Gated by:
 *   - `arqel.telemetry.metrics_endpoint_enabled` (opt-in)
 *   - Laravel's built-in `auth` + `web` middleware (registered at
 *     route declaration time)
 *   - A defensive `viewMetrics` Gate check — if the gate is
 *     defined the user must pass it, otherwise we fall through
 *     and rely solely on `auth`.
 */
final class MetricsController
{
    public function __invoke(PrometheusExporter $exporter): Response
    {
        $enabled = (bool) config('arqel.telemetry.metrics_endpoint_enabled', false);
        if (! $enabled) {
            abort(404);
        }

        if (Gate::has('viewMetrics') && ! Gate::allows('viewMetrics')) {
            abort(403);
        }

        return new Response(
            $exporter->export(),
            200,
            ['content-type' => 'text/plain; version=0.0.4'],
        );
    }
}

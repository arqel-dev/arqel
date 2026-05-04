<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\MetricsController;
use Arqel\Core\Telemetry\MetricsCollector;
use Arqel\Core\Telemetry\PrometheusExporter;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The endpoint route boots inside the framework's `web` middleware
 * group, which pulls Inertia and other infrastructure that is not
 * available in this barebones Testbench setup. We therefore drive
 * the controller through the container directly — that exercises
 * its branching logic (404 toggle, 403 gate, 200 success body)
 * without booting the full HTTP kernel.
 */

it('aborts 404 when metrics endpoint is disabled', function (): void {
    config()->set('arqel.telemetry.metrics_endpoint_enabled', false);

    $controller = new MetricsController;
    $exporter = $this->app->make(PrometheusExporter::class);
    assert($exporter instanceof PrometheusExporter);

    $controller($exporter);
})->throws(NotFoundHttpException::class);

it('returns 200 with prometheus content-type when enabled', function (): void {
    config()->set('arqel.telemetry.metrics_endpoint_enabled', true);

    $collector = $this->app->make(MetricsCollector::class);
    assert($collector instanceof MetricsCollector);
    $collector->counter('arqel_test_total', 7.0, ['env' => 'test']);

    $controller = new MetricsController;
    $exporter = $this->app->make(PrometheusExporter::class);
    assert($exporter instanceof PrometheusExporter);

    $response = $controller($exporter);

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('content-type'))->toBe('text/plain; version=0.0.4');
    expect((string) $response->getContent())->toContain('arqel_test_total');
    expect((string) $response->getContent())->toContain('env="test"');
});

it('aborts 403 when viewMetrics gate is defined and denies', function (): void {
    config()->set('arqel.telemetry.metrics_endpoint_enabled', true);
    \Illuminate\Support\Facades\Gate::define('viewMetrics', fn () => false);

    $controller = new MetricsController;
    $exporter = $this->app->make(PrometheusExporter::class);
    assert($exporter instanceof PrometheusExporter);

    try {
        $controller($exporter);
        expect(true)->toBeFalse('Expected HttpException 403');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('returns empty body when endpoint enabled but no metrics recorded', function (): void {
    config()->set('arqel.telemetry.metrics_endpoint_enabled', true);

    $controller = new MetricsController;
    $exporter = $this->app->make(PrometheusExporter::class);
    assert($exporter instanceof PrometheusExporter);

    $response = $controller($exporter);

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->getContent())->toBe('');
});

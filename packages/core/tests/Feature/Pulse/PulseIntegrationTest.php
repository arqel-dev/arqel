<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Feature\Pulse;

use Arqel\Core\Pulse\Cards\ArqelAiTokensCard;
use Arqel\Core\Pulse\Cards\ArqelJobMetricsCard;
use Arqel\Core\Pulse\Cards\ArqelResourcesCard;
use Arqel\Core\Pulse\Cards\ArqelSlowQueriesCard;
use Arqel\Core\Pulse\Cards\ArqelTopActionsCard;
use Arqel\Core\Pulse\PulseIntegration;
use Arqel\Core\Pulse\Recorders\ArqelActionRecorder;
use Arqel\Core\Pulse\Recorders\ArqelAiUsageRecorder;
use stdClass;

it('reports Pulse as unavailable when laravel/pulse is not installed', function (): void {
    // The Testbench runtime in this package does not require pulse.
    if (class_exists(\Laravel\Pulse\Pulse::class)) {
        $this->markTestSkipped('Laravel Pulse class is unexpectedly present.');
    }

    $integration = app(PulseIntegration::class);
    assert($integration instanceof PulseIntegration);

    expect($integration->isAvailable())->toBeFalse();
    expect($integration->pulseVersion())->toBeNull();
});

it('returns true from isAvailable when Pulse is aliased into the runtime', function (): void {
    if (! class_exists(\Laravel\Pulse\Pulse::class)) {
        class_alias(stdClass::class, \Laravel\Pulse\Pulse::class);
    }

    $integration = app(PulseIntegration::class);
    assert($integration instanceof PulseIntegration);

    expect($integration->isAvailable())->toBeTrue();
});

it('register() is a silent no-op when Pulse is unavailable', function (): void {
    $integration = new PulseIntegration;

    // Must not throw, must not produce output.
    $integration->register(app());

    expect(true)->toBeTrue();
});

it('exposes the canonical list of card tags', function (): void {
    $integration = new PulseIntegration;

    $tags = $integration->registeredCardTags();

    expect($tags)->toBe([
        'arqel-resources-card',
        'arqel-top-actions-card',
        'arqel-ai-tokens-card',
        'arqel-job-metrics-card',
        'arqel-slow-queries-card',
    ]);
});

it('declares the canonical list of recorders', function (): void {
    expect(PulseIntegration::RECORDERS)->toBe([
        ArqelActionRecorder::class,
        ArqelAiUsageRecorder::class,
    ]);
});

it('binds PulseIntegration as a singleton', function (): void {
    $a = app(PulseIntegration::class);
    $b = app(PulseIntegration::class);

    expect($a)->toBe($b);
});

it('autoloads every card class without requiring laravel/pulse', function (): void {
    // The stub bridge in src/Pulse/stubs.php aliases the absent
    // Pulse parent so the cards can be reflected on a runtime
    // without `laravel/pulse`.
    expect(class_exists(ArqelResourcesCard::class))->toBeTrue();
    expect(class_exists(ArqelTopActionsCard::class))->toBeTrue();
    expect(class_exists(ArqelAiTokensCard::class))->toBeTrue();
    expect(class_exists(ArqelJobMetricsCard::class))->toBeTrue();
    expect(class_exists(ArqelSlowQueriesCard::class))->toBeTrue();
});

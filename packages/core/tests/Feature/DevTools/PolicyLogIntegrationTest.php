<?php

declare(strict_types=1);

use Arqel\Core\DevTools\DevToolsPayloadBuilder;
use Arqel\Core\DevTools\PolicyLogCollector;
use Illuminate\Support\Facades\Gate;

it('registers a Gate::after listener in local environment', function (): void {
    $this->app->detectEnvironment(fn () => 'local');
    // Re-run booted callbacks to pick up the new env.
    /** @var Arqel\Core\ArqelServiceProvider $provider */
    $provider = $this->app->getProvider(Arqel\Core\ArqelServiceProvider::class);
    $provider->packageBooted();

    Gate::define('demo-allow', fn () => true);

    Gate::allows('demo-allow', ['ctx']);

    /** @var PolicyLogCollector $collector */
    $collector = $this->app->make(PolicyLogCollector::class);
    $entries = $collector->all();

    expect($entries)->not->toBeEmpty();

    $abilities = array_column($entries, 'ability');
    expect($abilities)->toContain('demo-allow');
});

it('does not record policy events in non-local environments', function (): void {
    // ServiceProvider::registerDevToolsServices early-returned during
    // boot because the testing env is `testing`. Sanity check the env
    // and verify no recording happens for a fresh Gate definition.
    expect($this->app->environment('local'))->toBeFalse();

    $collector = $this->app->make(PolicyLogCollector::class);
    $collector->flush();

    Gate::define('demo-skip', fn () => true);
    Gate::allows('demo-skip');

    expect($collector->all())->toBe([]);
});

it('builds a __devtools payload only in local environment', function (): void {
    /** @var DevToolsPayloadBuilder $builder */
    $builder = $this->app->make(DevToolsPayloadBuilder::class);

    expect($builder->build())->toBeNull();

    $this->app->detectEnvironment(fn () => 'local');

    $payload = $builder->build();

    expect($payload)->toBeArray()
        ->and($payload)->toHaveKeys(['policyLog', 'queryCount', 'memoryUsage'])
        ->and($payload['policyLog'])->toBeArray()
        ->and($payload['queryCount'])->toBeInt()
        ->and($payload['memoryUsage'])->toBeInt();
});

it('exposes policyLog entries through the __devtools payload in local', function (): void {
    $this->app->detectEnvironment(fn () => 'local');

    $collector = $this->app->make(PolicyLogCollector::class);
    $collector->flush();
    $collector->record('view-users', ['ctx'], true, [
        ['file' => '/app/X.php', 'line' => 10, 'class' => 'X', 'function' => 'do'],
    ]);

    /** @var DevToolsPayloadBuilder $builder */
    $builder = $this->app->make(DevToolsPayloadBuilder::class);
    $payload = $builder->build();

    expect($payload)->not->toBeNull()
        ->and($payload['policyLog'])->toHaveCount(1)
        ->and($payload['policyLog'][0]['ability'])->toBe('view-users')
        ->and($payload['policyLog'][0]['result'])->toBeTrue();
});

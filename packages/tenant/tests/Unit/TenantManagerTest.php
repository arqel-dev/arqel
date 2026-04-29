<?php

declare(strict_types=1);

use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\Events\TenantForgotten;
use Arqel\Tenant\Events\TenantResolved;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;

/**
 * In-memory `TenantResolver` stub that returns a pre-configured
 * Model and counts how many times `resolve()` was hit.
 */
function fakeResolver(?Tenant $tenant = null, string $identifier = 'fixture'): TenantResolver
{
    return new class($tenant, $identifier) implements TenantResolver
    {
        public int $resolveCalls = 0;

        public function __construct(
            private readonly ?Tenant $tenant,
            private readonly string $identifier,
        ) {}

        public function resolve(Request $request): ?Model
        {
            $this->resolveCalls++;

            return $this->tenant;
        }

        public function identifierFor(Model $tenant): string
        {
            return $this->identifier;
        }
    };
}

/**
 * Minimal in-memory dispatcher: records every event in a list
 * we can inspect from the tests. Avoids pulling Laravel's
 * concrete dispatcher (which expects a container, queue, etc).
 */
function recordingDispatcher(array &$captured): Illuminate\Contracts\Events\Dispatcher
{
    return new class($captured) implements Illuminate\Contracts\Events\Dispatcher
    {
        public function __construct(private array &$captured) {}

        public function listen($events, $listener = null): void {}

        public function hasListeners($eventName): bool
        {
            return false;
        }

        public function subscribe($subscriber): void {}

        public function until($event, $payload = []): mixed
        {
            return null;
        }

        public function dispatch($event, $payload = [], $halt = false): mixed
        {
            $this->captured[] = $event;

            return null;
        }

        public function push($event, $payload = []): void {}

        public function flush($event): void {}

        public function forget($event): void {}

        public function forgetPushed(): void {}
    };
}

it('starts with no current tenant and resolved=false', function (): void {
    $manager = new TenantManager;

    expect($manager->current())->toBeNull()
        ->and($manager->hasCurrent())->toBeFalse()
        ->and($manager->resolved())->toBeFalse()
        ->and($manager->id())->toBeNull();
});

it('resolve() returns null when no resolver is bound', function (): void {
    $manager = new TenantManager;

    expect($manager->resolve(Request::create('/')))->toBeNull()
        ->and($manager->resolved())->toBeTrue();
});

it('resolve() delegates to the resolver and caches the result', function (): void {
    $tenant = new Tenant(['id' => 7]);
    $resolver = fakeResolver($tenant);
    $manager = new TenantManager($resolver);

    expect($manager->resolve(Request::create('/')))->toBe($tenant)
        ->and($manager->resolve(Request::create('/again')))->toBe($tenant)
        ->and($resolver->resolveCalls)->toBe(1)
        ->and($manager->resolved())->toBeTrue();
});

it('emits TenantResolved when the resolver returns a tenant', function (): void {
    $captured = [];
    $tenant = new Tenant(['id' => 1]);
    $manager = new TenantManager(fakeResolver($tenant), recordingDispatcher($captured));

    $manager->resolve(Request::create('/'));

    expect($captured)->toHaveCount(1)
        ->and($captured[0])->toBeInstanceOf(TenantResolved::class)
        ->and($captured[0]->tenant)->toBe($tenant);
});

it('emits no events when the resolver returns null', function (): void {
    $captured = [];
    $manager = new TenantManager(fakeResolver(null), recordingDispatcher($captured));

    $manager->resolve(Request::create('/'));

    expect($captured)->toBeEmpty();
});

it('set() overrides the current tenant and emits TenantResolved', function (): void {
    $captured = [];
    $tenant = new Tenant(['id' => 42]);
    $manager = new TenantManager(null, recordingDispatcher($captured));

    $manager->set($tenant);

    expect($manager->current())->toBe($tenant)
        ->and($manager->resolved())->toBeTrue()
        ->and($manager->id())->toBe(42)
        ->and($captured)->toHaveCount(1)
        ->and($captured[0])->toBeInstanceOf(TenantResolved::class);
});

it('set(null) clears state and emits TenantForgotten when there was a tenant', function (): void {
    $captured = [];
    $manager = new TenantManager(null, recordingDispatcher($captured));
    $manager->set(new Tenant(['id' => 1]));

    $manager->set(null);

    expect($manager->current())->toBeNull()
        ->and($manager->resolved())->toBeFalse()
        ->and($captured)->toHaveCount(2)
        ->and($captured[1])->toBeInstanceOf(TenantForgotten::class);
});

it('set() does not re-emit when the same tenant instance is passed twice', function (): void {
    $captured = [];
    $tenant = new Tenant(['id' => 1]);
    $manager = new TenantManager(null, recordingDispatcher($captured));

    $manager->set($tenant);
    $manager->set($tenant);

    expect($captured)->toHaveCount(1);
});

it('forget() clears state and emits TenantForgotten', function (): void {
    $captured = [];
    $manager = new TenantManager(null, recordingDispatcher($captured));
    $manager->set(new Tenant(['id' => 1]));

    $manager->forget();

    expect($manager->current())->toBeNull()
        ->and($manager->resolved())->toBeFalse()
        ->and($captured[1])->toBeInstanceOf(TenantForgotten::class);
});

it('forget() is a no-op event-wise when no tenant was set', function (): void {
    $captured = [];
    $manager = new TenantManager(null, recordingDispatcher($captured));

    $manager->forget();

    expect($captured)->toBeEmpty();
});

it('runFor() swaps the tenant inside the closure and restores it after', function (): void {
    $outer = new Tenant(['id' => 1]);
    $inner = new Tenant(['id' => 99]);
    $manager = new TenantManager;
    $manager->set($outer);

    $seen = $manager->runFor($inner, fn () => $manager->current());

    expect($seen)->toBe($inner)
        ->and($manager->current())->toBe($outer);
});

it('runFor() restores the previous tenant even when the closure throws', function (): void {
    $outer = new Tenant(['id' => 1]);
    $manager = new TenantManager;
    $manager->set($outer);

    expect(fn () => $manager->runFor(
        new Tenant(['id' => 99]),
        function (): never {
            throw new RuntimeException('boom');
        },
    ))->toThrow(RuntimeException::class, 'boom');

    expect($manager->current())->toBe($outer);
});

it('currentOrFail throws when no tenant is set', function (): void {
    expect(fn () => (new TenantManager)->currentOrFail())
        ->toThrow(LogicException::class);
});

it('currentOrFail returns the current tenant when present', function (): void {
    $manager = new TenantManager;
    $tenant = new Tenant(['id' => 1]);
    $manager->set($tenant);

    expect($manager->currentOrFail())->toBe($tenant);
});

it('id() returns the model integer key when present, null when no tenant is set', function (): void {
    $manager = new TenantManager;
    expect($manager->id())->toBeNull();

    $manager->set(new Tenant(['id' => 7]));
    expect($manager->id())->toBe(7);
});

it('id() handles string keys when the model declares keyType=string', function (): void {
    $stringKeyTenant = new class extends Tenant
    {
        protected $keyType = 'string';

        public $incrementing = false;
    };
    $stringKeyTenant->forceFill(['id' => 'acme']);

    $manager = new TenantManager;
    $manager->set($stringKeyTenant);

    expect($manager->id())->toBe('acme');
});

it('identifier() falls back to (string) id() when no resolver is bound', function (): void {
    $manager = new TenantManager;
    $manager->set(new Tenant(['id' => 7]));

    expect($manager->identifier())->toBe('7');
});

it('identifier() delegates to the resolver when bound', function (): void {
    $tenant = new Tenant(['id' => 7]);
    $manager = new TenantManager(fakeResolver($tenant, 'tenant-7'));
    $manager->set($tenant);

    expect($manager->identifier())->toBe('tenant-7');
});

it('identifier() returns empty string when no tenant is set', function (): void {
    expect((new TenantManager)->identifier())->toBe('');
});

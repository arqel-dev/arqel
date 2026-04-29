<?php

declare(strict_types=1);

use Arqel\Tenant\Contracts\SupportsTenantSwitching;
use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\Events\TenantSwitched;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * In-memory resolver: enumerates a fixed list of tenants and
 * reports `canSwitchTo` based on it. Avoids touching the DB so
 * the test suite stays close to unit-speed while still exercising
 * the HTTP routing + middleware stack.
 */
function makeFakeResolver(array $tenants): SupportsTenantSwitching&TenantResolver
{
    return new class($tenants) implements SupportsTenantSwitching, TenantResolver
    {
        public ?Model $switched = null;

        /** @param array<int, Model> $tenants */
        public function __construct(private array $tenants) {}

        public function resolve(Request $request): ?Model
        {
            return null;
        }

        public function modelClass(): string
        {
            return Tenant::class;
        }

        public function identifierFor(Model $tenant): string
        {
            return (string) $tenant->getKey();
        }

        public function findByIdentifier(string $value): ?Model
        {
            foreach ($this->tenants as $tenant) {
                if ((string) $tenant->getKey() === $value) {
                    return $tenant;
                }
            }

            return null;
        }

        public function availableFor(Authenticatable $user): array
        {
            return $this->tenants;
        }

        public function canSwitchTo(Authenticatable $user, Model $tenant): bool
        {
            foreach ($this->tenants as $candidate) {
                if ($candidate->getKey() === $tenant->getKey()) {
                    return true;
                }
            }

            return false;
        }

        public function switchTo(Authenticatable $user, Model $tenant): void
        {
            $this->switched = $tenant;
        }
    };
}

beforeEach(function (): void {
    $this->tenantA = new Tenant(['id' => 1, 'name' => 'Acme', 'slug' => 'acme', 'logo' => null]);
    $this->tenantB = new Tenant(['id' => 2, 'name' => 'Globex', 'slug' => 'globex', 'logo' => null]);

    $this->user = new Authenticated;
    $this->user->id = 1;

    $resolver = makeFakeResolver([$this->tenantA, $this->tenantB]);
    $this->app->instance(TenantManager::class, new TenantManager($resolver));
});

it('switches tenant on POST and dispatches TenantSwitched', function (): void {
    Event::fake([TenantSwitched::class]);

    $this->actingAs($this->user)
        ->post('/admin/tenants/2/switch')
        ->assertRedirect('/admin');

    Event::assertDispatched(
        TenantSwitched::class,
        fn (TenantSwitched $event): bool => $event->to->getKey() === 2 && $event->from === null,
    );
});

it('returns 404 for an unknown tenant id', function (): void {
    $this->actingAs($this->user)
        ->post('/admin/tenants/999/switch')
        ->assertNotFound();
});

it('serialises current and available tenants via direct controller call', function (): void {
    /*
     * We invoke the controller directly here instead of going through
     * the HTTP kernel: the `web` middleware stack registered by
     * ArqelServiceProvider includes Inertia, which fails to resolve
     * `Inertia\Ssr\Gateway` in this minimal testbench setup. The
     * controller's logic (the bit we actually want to cover) does not
     * depend on the middleware stack, so direct invocation is the
     * cleanest assertion at this scaffold layer.
     */
    $controller = new Arqel\Tenant\Http\Controllers\TenantSwitcherController;
    $request = Request::create('/admin/tenants/available', 'GET');
    $request->setUserResolver(fn () => $this->user);

    $response = $controller->list($request, app(TenantManager::class));
    $payload = $response->getData(true);

    expect($payload)->toHaveKeys(['current', 'available']);
    expect($payload['available'])->toHaveCount(2);
    expect($payload['available'][0])->toMatchArray(['id' => 1, 'name' => 'Acme', 'slug' => 'acme']);
    expect($payload['available'][1]['id'])->toBe(2);
});

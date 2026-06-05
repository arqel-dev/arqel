<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Fake tenant model + manager: duck-typed to the subset the middleware
 * needs (current() + availableFor()). Bound under the real class name so
 * the middleware resolves it without core depending on arqel-dev/tenant.
 */
final class FakeTenantForShare extends Model
{
    protected $guarded = [];
}

it('emits null tenant when no TenantManager is bound', function (): void {
    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'currentTenant');
    $ref->setAccessible(true);

    expect($ref->invoke($mw, Request::create('/admin')))->toBeNull();
});

it('emits {current, available} when a TenantManager is bound', function (): void {
    $acme = (new FakeTenantForShare)->forceFill(['id' => 1, 'name' => 'Acme', 'slug' => 'acme']);
    $globex = (new FakeTenantForShare)->forceFill(['id' => 2, 'name' => 'Globex', 'slug' => 'globex']);

    $manager = new class($acme, [$acme, $globex])
    {
        /** @param array<int, Model> $available */
        public function __construct(private Model $current, private array $available) {}

        public function current(): ?Model
        {
            return $this->current;
        }

        /** @return array<int, Model> */
        public function availableFor($user): array
        {
            return $this->available;
        }
    };

    app()->instance('Arqel\\Tenant\\TenantManager', $manager);

    $request = Request::create('/admin');
    $request->setUserResolver(fn () => (object) ['id' => 99]);

    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'currentTenant');
    $ref->setAccessible(true);

    $payload = $ref->invoke($mw, $request);

    expect($payload)->toMatchArray([
        'current' => ['id' => 1, 'name' => 'Acme', 'slug' => 'acme', 'logo' => null],
    ])->and($payload['available'])->toHaveCount(2)
        ->and($payload['available'][1]['name'])->toBe('Globex');

    app()->forgetInstance('Arqel\\Tenant\\TenantManager');
});

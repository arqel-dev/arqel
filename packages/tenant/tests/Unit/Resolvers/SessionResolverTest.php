<?php

declare(strict_types=1);

use Arqel\Tenant\Resolvers\SessionResolver;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;

function sessionResolverWithStub(string $sessionKey = 'current_tenant_id', ?Tenant $tenantToReturn = null): SessionResolver
{
    return new class('Arqel\\Tenant\\Tests\\Fixtures\\Tenant', 'id', $sessionKey, $tenantToReturn) extends SessionResolver
    {
        /** @var list<string> */
        public array $lookupCalls = [];

        public function __construct(
            string $modelClass,
            string $identifierColumn,
            string $sessionKey,
            private readonly ?Tenant $stub,
        ) {
            parent::__construct($modelClass, $identifierColumn, $sessionKey);
        }

        protected function findByIdentifier(string $value): ?Model
        {
            $this->lookupCalls[] = $value;

            return $this->stub;
        }
    };
}

function requestWithSession(): Request
{
    $request = Request::create('https://x.test/');
    $session = new Store('test', new ArraySessionHandler(60));
    $request->setLaravelSession($session);

    return $request;
}

it('returns null when the request has no session bound', function (): void {
    $resolver = sessionResolverWithStub();

    expect($resolver->resolve(Request::create('https://x.test/')))->toBeNull();
});

it('returns null when the configured session key is missing', function (): void {
    $resolver = sessionResolverWithStub();

    expect($resolver->resolve(requestWithSession()))->toBeNull();
});

it('reads the tenant identifier from the configured session key', function (): void {
    $tenant = new Tenant(['id' => 9]);
    $resolver = sessionResolverWithStub('current_tenant_id', $tenant);

    $request = requestWithSession();
    $request->session()->put('current_tenant_id', 9);

    expect($resolver->resolve($request))->toBe($tenant)
        ->and($resolver->lookupCalls)->toBe(['9']);
});

it('coerces scalar identifiers to string before looking up', function (): void {
    $tenant = new Tenant(['id' => 'acme']);
    $resolver = sessionResolverWithStub('current_tenant_id', $tenant);

    $request = requestWithSession();
    $request->session()->put('current_tenant_id', 'acme');

    expect($resolver->resolve($request))->toBe($tenant)
        ->and($resolver->lookupCalls)->toBe(['acme']);
});

it('returns null when the session value is non-scalar (array, object)', function (): void {
    $resolver = sessionResolverWithStub();

    $request = requestWithSession();
    $request->session()->put('current_tenant_id', ['nested' => true]);

    expect($resolver->resolve($request))->toBeNull();
});

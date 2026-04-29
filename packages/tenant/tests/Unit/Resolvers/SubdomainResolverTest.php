<?php

declare(strict_types=1);

use Arqel\Tenant\Resolvers\SubdomainResolver;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Test resolver that captures the value passed to findByIdentifier
 * and returns a stub Tenant — sidesteps the need for a real DB.
 */
function subdomainResolverWithStub(?string $centralDomain = null, ?Tenant $tenantToReturn = null): SubdomainResolver
{
    return new class('Arqel\\Tenant\\Tests\\Fixtures\\Tenant', 'subdomain', $centralDomain, $tenantToReturn) extends SubdomainResolver
    {
        /** @var list<string> */
        public array $lookupCalls = [];

        public function __construct(
            string $modelClass,
            string $identifierColumn,
            ?string $centralDomain,
            private readonly ?Tenant $stub,
        ) {
            parent::__construct($modelClass, $identifierColumn, $centralDomain);
        }

        protected function findByIdentifier(string $value): ?Model
        {
            $this->lookupCalls[] = $value;

            return $this->stub;
        }
    };
}

it('extracts the leftmost subdomain when host matches the central domain suffix', function (): void {
    $tenant = new Tenant(['subdomain' => 'acme']);
    $resolver = subdomainResolverWithStub('myapp.com', $tenant);

    $request = Request::create('https://acme.myapp.com/dashboard');

    expect($resolver->resolve($request))->toBe($tenant)
        ->and($resolver->lookupCalls)->toBe(['acme']);
});

it('returns null when host equals the central domain', function (): void {
    $resolver = subdomainResolverWithStub('myapp.com');

    $request = Request::create('https://myapp.com/');

    expect($resolver->resolve($request))->toBeNull();
});

it('treats www as not-a-tenant', function (): void {
    $resolver = subdomainResolverWithStub('myapp.com');

    $request = Request::create('https://www.myapp.com/');

    expect($resolver->resolve($request))->toBeNull();
});

it('returns null when host is outside the central domain (refuses to guess)', function (): void {
    $resolver = subdomainResolverWithStub('myapp.com');

    $request = Request::create('https://other.example.com/');

    expect($resolver->resolve($request))->toBeNull();
});

it('falls back to the leftmost label heuristic when no central domain is configured', function (): void {
    $tenant = new Tenant(['subdomain' => 'acme']);
    $resolver = subdomainResolverWithStub(null, $tenant);

    $request = Request::create('https://acme.foo.bar/');

    expect($resolver->resolve($request))->toBe($tenant);
});

it('refuses the heuristic when there are fewer than 3 host labels', function (): void {
    $resolver = subdomainResolverWithStub();

    $request = Request::create('https://example.com/');

    expect($resolver->resolve($request))->toBeNull();
});

it('lowercases the host before extracting the subdomain', function (): void {
    $tenant = new Tenant(['subdomain' => 'acme']);
    $resolver = subdomainResolverWithStub('myapp.com', $tenant);

    $request = Request::create('https://ACME.MyApp.com/');

    expect($resolver->resolve($request))->toBe($tenant)
        ->and($resolver->lookupCalls)->toBe(['acme']);
});

it('throws when the model class is not an Eloquent model', function (): void {
    expect(fn () => new SubdomainResolver(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});

it('identifierFor returns the configured column when present', function (): void {
    $resolver = new SubdomainResolver(Tenant::class, 'subdomain', 'myapp.com');
    $tenant = new Tenant(['subdomain' => 'acme']);

    expect($resolver->identifierFor($tenant))->toBe('acme');
});

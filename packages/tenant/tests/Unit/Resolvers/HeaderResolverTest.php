<?php

declare(strict_types=1);

use Arqel\Tenant\Resolvers\HeaderResolver;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

function headerResolverWithStub(string $header = 'X-Tenant-ID', ?Tenant $tenantToReturn = null): HeaderResolver
{
    return new class('Arqel\\Tenant\\Tests\\Fixtures\\Tenant', 'id', $header, $tenantToReturn) extends HeaderResolver
    {
        /** @var list<string> */
        public array $lookupCalls = [];

        public function __construct(
            string $modelClass,
            string $identifierColumn,
            string $header,
            private readonly ?Tenant $stub,
        ) {
            parent::__construct($modelClass, $identifierColumn, $header);
        }

        protected function findByIdentifier(string $value): ?Model
        {
            $this->lookupCalls[] = $value;

            return $this->stub;
        }
    };
}

it('reads the configured header and looks the tenant up by it', function (): void {
    $tenant = new Tenant(['id' => 42]);
    $resolver = headerResolverWithStub('X-Tenant-ID', $tenant);

    $request = Request::create('https://x.test/');
    $request->headers->set('X-Tenant-ID', '42');

    expect($resolver->resolve($request))->toBe($tenant)
        ->and($resolver->lookupCalls)->toBe(['42']);
});

it('returns null when the header is absent', function (): void {
    expect(headerResolverWithStub()->resolve(Request::create('https://x.test/')))->toBeNull();
});

it('returns null when the header is empty', function (): void {
    $request = Request::create('https://x.test/');
    $request->headers->set('X-Tenant-ID', '');

    expect(headerResolverWithStub()->resolve($request))->toBeNull();
});

it('honours a custom header name', function (): void {
    $tenant = new Tenant(['id' => 7]);
    $resolver = headerResolverWithStub('X-Workspace', $tenant);

    $request = Request::create('https://x.test/');
    $request->headers->set('X-Workspace', '7');

    expect($resolver->resolve($request))->toBe($tenant);
});

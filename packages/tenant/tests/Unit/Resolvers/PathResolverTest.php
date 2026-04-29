<?php

declare(strict_types=1);

use Arqel\Tenant\Resolvers\PathResolver;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * @param list<string> $ignoreSegments
 */
function pathResolverWithStub(array $ignoreSegments = [], ?Tenant $tenantToReturn = null): PathResolver
{
    return new class('Arqel\\Tenant\\Tests\\Fixtures\\Tenant', 'slug', $ignoreSegments, $tenantToReturn) extends PathResolver
    {
        /** @var list<string> */
        public array $lookupCalls = [];

        public function __construct(
            string $modelClass,
            string $identifierColumn,
            array $ignoreSegments,
            private readonly ?Tenant $stub,
        ) {
            parent::__construct($modelClass, $identifierColumn, $ignoreSegments);
        }

        protected function findByIdentifier(string $value): ?Model
        {
            $this->lookupCalls[] = $value;

            return $this->stub;
        }
    };
}

it('extracts the first path segment as the tenant identifier', function (): void {
    $tenant = new Tenant(['slug' => 'acme']);
    $resolver = pathResolverWithStub([], $tenant);

    expect($resolver->resolve(Request::create('https://x.test/acme/dashboard')))->toBe($tenant)
        ->and($resolver->lookupCalls)->toBe(['acme']);
});

it('returns null when the path is empty', function (): void {
    expect(pathResolverWithStub()->resolve(Request::create('https://x.test/')))->toBeNull();
});

it('skips segments listed in ignoreSegments', function (): void {
    $resolver = pathResolverWithStub(['admin', 'api']);

    expect($resolver->resolve(Request::create('https://x.test/admin/users')))->toBeNull()
        ->and($resolver->resolve(Request::create('https://x.test/api/posts')))->toBeNull();
});

it('compares ignoreSegments case-insensitively', function (): void {
    $resolver = pathResolverWithStub(['ADMIN']);

    expect($resolver->resolve(Request::create('https://x.test/admin/users')))->toBeNull();
});

it('lowercases the segment before looking up', function (): void {
    $tenant = new Tenant(['slug' => 'acme']);
    $resolver = pathResolverWithStub([], $tenant);

    $resolver->resolve(Request::create('https://x.test/ACME/x'));

    expect($resolver->lookupCalls)->toBe(['acme']);
});

<?php

declare(strict_types=1);

use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\Middleware\ResolveTenantMiddleware;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

function makeNullResolver(): TenantResolver
{
    return new class implements TenantResolver
    {
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
            return null;
        }
    };
}

/**
 * TENANT-014 coverage gap: existing tests covered required mode + the
 * mode-normalisation helper, but not the canonical happy path of MODE_OPTIONAL
 * letting an unresolved request through cleanly.
 */
it('lets unresolved requests through in MODE_OPTIONAL', function (): void {
    $manager = new TenantManager(makeNullResolver());
    $middleware = new ResolveTenantMiddleware($manager);

    $request = Request::create('/admin', 'GET');
    $reached = false;
    $next = function (Request $passed) use (&$reached, $request) {
        $reached = true;
        expect($passed)->toBe($request);

        return 'ok';
    };

    $result = $middleware->handle($request, $next, ResolveTenantMiddleware::MODE_OPTIONAL);

    expect($reached)->toBeTrue();
    expect($result)->toBe('ok');
    expect($manager->current())->toBeNull();
});

it('case-insensitively accepts OPTIONAL / Optional / optional', function (): void {
    $manager = new TenantManager(makeNullResolver());
    $middleware = new ResolveTenantMiddleware($manager);

    foreach (['optional', 'Optional', 'OPTIONAL', '  optional  '] as $variant) {
        $reached = false;
        $middleware->handle(
            Request::create('/admin', 'GET'),
            function () use (&$reached) {
                $reached = true;

                return 'ok';
            },
            $variant,
        );
        expect($reached)->toBeTrue("variant {$variant} did not reach next");
    }
});

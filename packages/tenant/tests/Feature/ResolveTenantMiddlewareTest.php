<?php

declare(strict_types=1);

use Arqel\Tenant\Contracts\TenantResolver;
use Arqel\Tenant\Exceptions\TenantNotFoundException;
use Arqel\Tenant\Middleware\ResolveTenantMiddleware;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

function tenantStubResolver(?Tenant $tenant): TenantResolver
{
    return new class($tenant) implements TenantResolver
    {
        public function __construct(private readonly ?Tenant $tenant) {}

        public function resolve(Request $request): ?Model
        {
            return $this->tenant;
        }

        public function identifierFor(Model $tenant): string
        {
            return 'fixture';
        }
    };
}

function middlewareWithTenant(?Tenant $tenant): ResolveTenantMiddleware
{
    return new ResolveTenantMiddleware(new TenantManager(tenantStubResolver($tenant)));
}

it('lets the request through when the resolver returns a tenant', function (): void {
    $middleware = middlewareWithTenant(new Tenant(['id' => 1]));
    $reached = false;

    $response = $middleware->handle(
        Request::create('https://x.test/'),
        function () use (&$reached) {
            $reached = true;

            return new Response('ok');
        },
    );

    expect($reached)->toBeTrue()
        ->and($response)->toBeInstanceOf(Response::class);
});

it('aborts with TenantNotFoundException when no tenant resolves and mode=required (default)', function (): void {
    $middleware = middlewareWithTenant(null);

    expect(fn () => $middleware->handle(
        Request::create('https://x.test/'),
        fn () => new Response('should not reach'),
    ))->toThrow(TenantNotFoundException::class);
});

it('lets the request through when no tenant resolves but mode=optional', function (): void {
    $middleware = middlewareWithTenant(null);
    $reached = false;

    $middleware->handle(
        Request::create('https://x.test/'),
        function () use (&$reached) {
            $reached = true;

            return new Response('ok');
        },
        'optional',
    );

    expect($reached)->toBeTrue();
});

it('treats unknown mode strings as required for safety', function (): void {
    $middleware = middlewareWithTenant(null);

    expect(fn () => $middleware->handle(
        Request::create('https://x.test/'),
        fn () => new Response('ok'),
        'lol',
    ))->toThrow(TenantNotFoundException::class);
});

it('mode parameter is case-insensitive and trim-tolerant', function (): void {
    $middleware = middlewareWithTenant(null);
    $reached = false;

    $middleware->handle(
        Request::create('https://x.test/'),
        function () use (&$reached) {
            $reached = true;

            return new Response('ok');
        },
        ' OPTIONAL ',
    );

    expect($reached)->toBeTrue();
});

it('TenantNotFoundException renders JSON 404 when the request expects JSON', function (): void {
    $exception = new TenantNotFoundException('No tenant.', identifier: 'acme.test');

    $request = Request::create('https://x.test/');
    $request->headers->set('Accept', 'application/json');

    $response = $exception->render($request);

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);

    /** @var Illuminate\Http\JsonResponse $response */
    $payload = $response->getData(true);

    expect($payload['message'])->toBe('No tenant.')
        ->and($payload['tenantIdentifier'])->toBe('acme.test');
});

it('TenantNotFoundException renders plain 404 when no Inertia view is published', function (): void {
    $exception = new TenantNotFoundException;
    $response = $exception->render(Request::create('https://x.test/'));

    expect($response->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
});

it('registers the arqel.tenant middleware alias on the router', function (): void {
    /** @var Illuminate\Routing\Router $router */
    $router = app(Illuminate\Routing\Router::class);
    $aliases = $router->getMiddleware();

    expect($aliases)->toHaveKey('arqel.tenant')
        ->and($aliases['arqel.tenant'])->toBe(ResolveTenantMiddleware::class);
});

<?php

declare(strict_types=1);

use Arqel\Tenant\Middleware\RequireTenantFeature;
use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\FeatureGatedTenant;
use Arqel\Tenant\Tests\Fixtures\Tenant as PlainTenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

function buildFeatureMiddleware(?object $tenant): RequireTenantFeature
{
    $manager = new TenantManager;
    if ($tenant !== null) {
        // @phpstan-ignore-next-line — fixture extends Model
        $manager->set($tenant);
    }

    return new RequireTenantFeature($manager);
}

it('aborts with 404 when there is no current tenant', function (): void {
    $middleware = buildFeatureMiddleware(null);

    try {
        $middleware->handle(
            Request::create('https://x.test/'),
            fn () => new Response('next'),
            'analytics',
        );
        expect(false)->toBeTrue('expected HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(Response::HTTP_NOT_FOUND);
    }
});

it('aborts with 500 when the tenant model does not implement hasFeature', function (): void {
    $middleware = buildFeatureMiddleware(new PlainTenant(['id' => 1]));

    try {
        $middleware->handle(
            Request::create('https://x.test/'),
            fn () => new Response('next'),
            'analytics',
        );
        expect(false)->toBeTrue('expected HttpException');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(Response::HTTP_INTERNAL_SERVER_ERROR);
        expect($e->getMessage())->toContain('HasFeatures');
    }
});

it('returns 402 with structured payload when the feature is not enabled', function (): void {
    $tenant = new FeatureGatedTenant(['id' => 1, 'features' => ['reports']]);
    $middleware = buildFeatureMiddleware($tenant);

    $response = $middleware->handle(
        Request::create('https://x.test/'),
        fn () => new Response('should not reach'),
        'analytics',
    );

    expect($response->getStatusCode())->toBe(Response::HTTP_PAYMENT_REQUIRED);

    /** @var Illuminate\Http\JsonResponse $response */
    $payload = $response->getData(true);

    expect($payload['error'])->toBe('feature_not_available')
        ->and($payload['feature'])->toBe('analytics')
        ->and($payload['message'])->toContain('analytics');
});

it('lets the request through when the feature is enabled', function (): void {
    $tenant = new FeatureGatedTenant(['id' => 1, 'features' => ['analytics']]);
    $middleware = buildFeatureMiddleware($tenant);

    $reached = false;
    $response = $middleware->handle(
        Request::create('https://x.test/'),
        function () use (&$reached) {
            $reached = true;

            return new Response('ok');
        },
        'analytics',
    );

    expect($reached)->toBeTrue();
    expect($response)->toBeInstanceOf(Response::class);
});

it('registers the arqel.tenant.feature middleware alias on the router', function (): void {
    /** @var Illuminate\Routing\Router $router */
    $router = app(Illuminate\Routing\Router::class);
    $aliases = $router->getMiddleware();

    expect($aliases)->toHaveKey('arqel.tenant.feature')
        ->and($aliases['arqel.tenant.feature'])->toBe(RequireTenantFeature::class);
});

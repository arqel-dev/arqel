<?php

declare(strict_types=1);

use Illuminate\Routing\RouteCollection;

it('registers the arqel.resources.bulk route name', function (): void {
    /** @var RouteCollection $routes */
    $routes = app('router')->getRoutes();

    $names = collect($routes)
        ->map(fn ($route) => $route->getName())
        ->filter()
        ->values()
        ->all();

    expect($names)->toContain('arqel.resources.bulk');
});

it('the bulk route is bound as POST under the panel path', function (): void {
    $route = app('router')->getRoutes()->getByName('arqel.resources.bulk');

    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain('POST')
        ->and($route->uri())->toContain('{resource}/bulk/{action}');
});

it('returns 404 when the resource slug is not registered', function (): void {
    $response = $this->withoutMiddleware()
        ->post('/admin/unknown/bulk/delete', ['record_ids' => [1, 2]]);

    expect($response->getStatusCode())->toBe(404);
});

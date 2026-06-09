<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Illuminate\Support\Facades\Route;

/**
 * Issue #234 — routing-precedence trap.
 *
 * The polymorphic `GET admin/{resource}` route used to constrain its
 * `{resource}` wildcard to "anything that is not a reserved auth slug".
 * That regex still matched single-segment app routes such as
 * `/admin/versions-demo`, capturing them as `resource="versions-demo"`
 * and 404-ing (no such resource) — silently shadowing the app's own
 * route.
 *
 * After the fix the wildcard is constrained to the *registered* resource
 * slugs, so an unknown segment falls through to the app route.
 */
beforeEach(function (): void {
    /** @var ResourceRegistry $registry */
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $registry->register(PostResource::class);

    // Re-register the resource routes now that the registry knows about
    // `posts` — this mirrors the provider's post-boot ordering, where
    // resources are synced before routes are mounted.
    $provider = app()->getProvider(Arqel\Core\ArqelServiceProvider::class);
    $method = new ReflectionMethod($provider, 'registerResourceRoutes');
    $method->setAccessible(true);
    $method->invoke($provider);

    // App-defined route living *under* the panel prefix but NOT a
    // registered resource. It must win over the polymorphic wildcard.
    Route::get('admin/custom-page', fn (): string => 'app-custom-page')
        ->name('app.custom-page');
});

it('matches a real resource slug through the polymorphic wildcard', function (): void {
    $matched = Route::getRoutes()->match(
        Illuminate\Http\Request::create('admin/posts', 'GET'),
    );

    expect($matched->getName())->toBe('arqel.resources.index')
        ->and($matched->parameter('resource'))->toBe('posts');
});

it('falls through to the app route for a non-resource segment', function (): void {
    $matched = Route::getRoutes()->match(
        Illuminate\Http\Request::create('admin/custom-page', 'GET'),
    );

    expect($matched->getName())->toBe('app.custom-page');
});

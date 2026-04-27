<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Models\Post;
use Arqel\Core\Tests\Fixtures\Models\User;
use Arqel\Core\Tests\Fixtures\NotAResource;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

beforeEach(function (): void {
    $this->registry = new ResourceRegistry;
});

it('registers a resource class', function (): void {
    $this->registry->register(UserResource::class);

    expect($this->registry->all())->toBe([UserResource::class])
        ->and($this->registry->has(UserResource::class))->toBeTrue();
});

it('rejects classes that do not implement HasResource', function (): void {
    $this->registry->register(NotAResource::class);
})->throws(InvalidArgumentException::class, 'must implement');

it('is idempotent when the same class is registered twice', function (): void {
    $this->registry->register(UserResource::class);
    $this->registry->register(UserResource::class);

    expect($this->registry->all())->toHaveCount(1);
});

it('registers many resources at once', function (): void {
    $this->registry->registerMany([UserResource::class, PostResource::class]);

    expect($this->registry->all())
        ->toHaveCount(2)
        ->toContain(UserResource::class, PostResource::class);
});

it('finds a resource by its model class', function (): void {
    $this->registry->registerMany([UserResource::class, PostResource::class]);

    expect($this->registry->findByModel(User::class))->toBe(UserResource::class)
        ->and($this->registry->findByModel(Post::class))->toBe(PostResource::class);
});

it('returns null when no resource matches the model class', function (): void {
    $this->registry->register(UserResource::class);

    expect($this->registry->findByModel('App\\Models\\Missing'))->toBeNull();
});

it('finds a resource by its slug', function (): void {
    $this->registry->registerMany([UserResource::class, PostResource::class]);

    expect($this->registry->findBySlug('users'))->toBe(UserResource::class)
        ->and($this->registry->findBySlug('posts'))->toBe(PostResource::class);
});

it('returns null when no resource matches the slug', function (): void {
    $this->registry->register(UserResource::class);

    expect($this->registry->findBySlug('missing'))->toBeNull();
});

it('clears all registered resources', function (): void {
    $this->registry->registerMany([UserResource::class, PostResource::class]);
    $this->registry->clear();

    expect($this->registry->all())->toBe([])
        ->and($this->registry->has(UserResource::class))->toBeFalse();
});

it('discovers resources in a directory using PSR-4', function (): void {
    $this->registry->discover(
        path: __DIR__.'/../Fixtures/Resources',
        namespace: 'Arqel\\Core\\Tests\\Fixtures\\Resources',
    );

    expect($this->registry->all())
        ->toHaveCount(2)
        ->toContain(UserResource::class, PostResource::class);
});

it('skips classes in the discovery path that do not implement HasResource', function (): void {
    $this->registry->discover(
        path: __DIR__.'/../Fixtures',
        namespace: 'Arqel\\Core\\Tests\\Fixtures',
    );

    expect($this->registry->has(NotAResource::class))->toBeFalse()
        ->and($this->registry->all())->toContain(UserResource::class, PostResource::class);
});

it('returns silently when discovering a non-existent directory', function (): void {
    $this->registry->discover(
        path: __DIR__.'/does-not-exist',
        namespace: 'Arqel\\Core\\Tests\\Missing',
    );

    expect($this->registry->all())->toBe([]);
});

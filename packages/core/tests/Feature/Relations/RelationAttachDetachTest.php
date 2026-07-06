<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\RelationController;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Models\RelTag;
use Arqel\Core\Tests\Fixtures\Resources\RelPostResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Task 7: `RelationController::attach` + `detach` (BelongsToMany) + a 405
 * guard on non-belongsToMany relations.
 *
 * Mirrors the established feature-test convention in this suite
 * (RelationIndexTest / RelationStoreTest / RelationUpdateDestroyTest): the
 * controller method is invoked directly rather than driven through a real
 * HTTP request, since route registration for the polymorphic `{resource}`
 * routes happens on `app->booted()` off the Panel registry, which none of
 * the existing feature tests wire up. Bare named routes are still
 * registered so `route()` name resolution works.
 *
 * This is also the first test in the suite to exercise the belongsToMany
 * branch of `RelationManager::relationType()` (Task 1), previously only
 * covered for hasMany.
 */
beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RelPostResource::class);

    Illuminate\Support\Facades\Route::post('/{resource}/{parent}/relations/{relation}/attach', fn () => 'ok')
        ->name('arqel.resources.relations.attach');
    Illuminate\Support\Facades\Route::delete('/{resource}/{parent}/relations/{relation}/{related}/detach', fn () => 'ok')
        ->name('arqel.resources.relations.detach');
});

it('attaches an existing tag to the post via the pivot', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $tag = RelTag::create(['name' => 'php']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.attach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'tags',
    ]), 'POST', ['related' => $tag->id]);

    $controller->attach($request, 'rel-posts', $post->id, 'tags');

    expect($post->tags()->whereKey($tag->id)->exists())->toBeTrue();
});

it('detaches without deleting the tag record', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $tag = RelTag::create(['name' => 'php']);
    $post->tags()->attach($tag->id);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.detach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'tags', 'related' => $tag->id,
    ]), 'DELETE');

    $controller->detach($request, 'rel-posts', $post->id, 'tags', $tag->id);

    expect($post->tags()->whereKey($tag->id)->exists())->toBeFalse()
        ->and(RelTag::find($tag->id))->not->toBeNull(); // record survives
});

it('405s when attaching on a hasMany relation', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.attach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]), 'POST', ['related' => 1]);

    try {
        $controller->attach($request, 'rel-posts', $post->id, 'comments');
        $this->fail('Expected an HttpException 405 for attach on a hasMany relation.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(405);
    }
});

it('405s when detaching on a hasMany relation', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.detach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => 1,
    ]), 'DELETE');

    try {
        $controller->detach($request, 'rel-posts', $post->id, 'comments', 1);
        $this->fail('Expected an HttpException 405 for detach on a hasMany relation.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(405);
    }
});

<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\RelationController;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Models\RelComment;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Resources\RelPostResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Task 4: `RelationController::index` — the controller spine (resolve
 * manager → scope to parent → authorize) every later relation endpoint
 * reuses.
 *
 * Mirrors the established feature-test convention in this suite
 * (RowActionDispatchTest / FieldWriteAuthorizationTest): the controller
 * method is invoked directly rather than driven through a real HTTP
 * request, since route registration for the polymorphic `{resource}`
 * routes happens on `app->booted()` off the Panel registry, which none
 * of the existing feature tests wire up. A bare named route is still
 * registered so `route()` name resolution works, matching the same
 * existing convention.
 */
beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RelPostResource::class);

    Illuminate\Support\Facades\Route::get('/{resource}/{parent}/relations/{relation}', fn () => 'ok')
        ->name('arqel.resources.relations.index');
});

it('lists only the parent record\'s related records', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $other = RelPost::create(['title' => 'B']);
    RelComment::create(['post_id' => $post->id, 'body' => 'mine']);
    RelComment::create(['post_id' => $other->id, 'body' => 'theirs']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.index', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]));

    $response = $controller->index($request, 'rel-posts', $post->id, 'comments');
    $payload = $response->getData(true);

    expect($payload['records'])->not->toBeEmpty();

    $bodies = collect($payload['records'])->pluck('body')->all();

    expect($bodies)->toContain('mine')
        ->and($bodies)->not->toContain('theirs');
});

it('404s for a relation not in the resource allowlist', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.index', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'unknownrel',
    ]));

    $controller->index($request, 'rel-posts', $post->id, 'unknownrel');
})->throws(HttpException::class);

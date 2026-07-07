<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\RelationController;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Models\RelComment;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Resources\RelPostResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Deny-path coverage for `RelationController::authorize()` — the method
 * used by the six CRUD verbs (index/create/store/edit/update/destroy).
 *
 * The rest of the suite covered only the fail-OPEN path (no gate/policy →
 * allowed) for these verbs; the record-level deny test lived on
 * `authorizeAttach()` (a different method, used by attach/detach). A
 * regression that inverted or broke `Gate::denies()` in `authorize()`
 * would open an authz bypass across every relation CRUD endpoint and go
 * uncaught. These tests pin the fail-CLOSED guarantee: a registered
 * ability that denies must produce a 403 for each verb.
 *
 * A denying ability is registered via `Gate::define(...)` (not
 * `Gate::policy(...)`): the controller is invoked directly (same process),
 * and `Gate::define` is the mechanism `authorize()` documents as the
 * two-tier trigger (a gate rule with no Policy class must still enforce).
 */
beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RelPostResource::class);

    foreach ([
        'arqel.resources.relations.index' => '/{resource}/{parent}/relations/{relation}',
        'arqel.resources.relations.create' => '/{resource}/{parent}/relations/{relation}/create',
        'arqel.resources.relations.store' => '/{resource}/{parent}/relations/{relation}',
        'arqel.resources.relations.edit' => '/{resource}/{parent}/relations/{relation}/{related}/edit',
        'arqel.resources.relations.update' => '/{resource}/{parent}/relations/{relation}/{related}',
        'arqel.resources.relations.destroy' => '/{resource}/{parent}/relations/{relation}/{related}',
    ] as $name => $uri) {
        Route::get($uri, fn () => 'ok')->name($name);
    }
});

/**
 * @return array{RelationController, RelPost, RelComment}
 */
function denyFixture(): array
{
    $post = RelPost::create(['title' => 'A']);
    $comment = RelComment::create(['post_id' => $post->id, 'body' => 'mine']);

    return [app(RelationController::class), $post, $comment];
}

it('forbids index (403) when the viewAny ability denies', function (): void {
    Gate::define('viewAny', static fn (mixed $user, mixed $subject = null): bool => false);
    [$controller, $post] = denyFixture();

    $request = Request::create(route('arqel.resources.relations.index', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]));

    try {
        $controller->index($request, 'rel-posts', $post->id, 'comments');
        $this->fail('Expected a 403 HttpException for a denied viewAny.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('forbids create (403) when the create ability denies', function (): void {
    Gate::define('create', static fn (mixed $user, mixed $subject = null): bool => false);
    [$controller, $post] = denyFixture();

    $request = Request::create(route('arqel.resources.relations.create', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]));

    try {
        $controller->create($request, 'rel-posts', $post->id, 'comments');
        $this->fail('Expected a 403 HttpException for a denied create.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('forbids store (403) when the create ability denies, persisting nothing', function (): void {
    Gate::define('create', static fn (mixed $user, mixed $subject = null): bool => false);
    [$controller, $post] = denyFixture();

    $request = Request::create(route('arqel.resources.relations.store', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]), 'POST', ['body' => 'blocked']);

    try {
        $controller->store($request, 'rel-posts', $post->id, 'comments');
        $this->fail('Expected a 403 HttpException for a denied store.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    expect(RelComment::where('body', 'blocked')->exists())->toBeFalse();
});

it('forbids edit (403) when the update ability denies', function (): void {
    Gate::define('update', static fn (mixed $user, mixed $subject = null): bool => false);
    [$controller, $post, $comment] = denyFixture();

    $request = Request::create(route('arqel.resources.relations.edit', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $comment->id,
    ]));

    try {
        $controller->edit($request, 'rel-posts', $post->id, 'comments', $comment->id);
        $this->fail('Expected a 403 HttpException for a denied edit.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('forbids update (403) when the update ability denies, mutating nothing', function (): void {
    Gate::define('update', static fn (mixed $user, mixed $subject = null): bool => false);
    [$controller, $post, $comment] = denyFixture();

    $request = Request::create(route('arqel.resources.relations.update', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $comment->id,
    ]), 'PUT', ['body' => 'tampered']);

    try {
        $controller->update($request, 'rel-posts', $post->id, 'comments', $comment->id);
        $this->fail('Expected a 403 HttpException for a denied update.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    expect($comment->fresh()->body)->toBe('mine');
});

it('forbids destroy (403) when the delete ability denies, deleting nothing', function (): void {
    Gate::define('delete', static fn (mixed $user, mixed $subject = null): bool => false);
    [$controller, $post, $comment] = denyFixture();

    $request = Request::create(route('arqel.resources.relations.destroy', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $comment->id,
    ]), 'DELETE');

    try {
        $controller->destroy($request, 'rel-posts', $post->id, 'comments', $comment->id);
        $this->fail('Expected a 403 HttpException for a denied destroy.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    expect($comment->fresh())->not->toBeNull();
});

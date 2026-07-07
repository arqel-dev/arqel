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
 * Task 6: `RelationController::edit` + `update` + `destroy` — completes
 * parent-scoped CRUD for the child record.
 *
 * Mirrors the established feature-test convention in this suite
 * (RelationIndexTest / RelationStoreTest): the controller method is invoked
 * directly rather than driven through a real HTTP request, since route
 * registration for the polymorphic `{resource}` routes happens on
 * `app->booted()` off the Panel registry, which none of the existing
 * feature tests wire up. Bare named routes are still registered so
 * `route()` name resolution works, matching the same existing convention.
 *
 * ANTI-IDOR: `findRelated()` resolves the related record via
 * `$parentModel->{relation}()->find($related)` — i.e. scoped to the
 * PARENT's relation query, not a global `Model::find()`. A related id that
 * belongs to a DIFFERENT parent is simply absent from that scoped query.
 * `findRelated()` then does `abort_if($record === null, Response::HTTP_NOT_FOUND)`
 * explicitly, rather than relying on `findOrFail()`'s `ModelNotFoundException`
 * (which only becomes an HTTP 404 via Laravel's exception handler — absent
 * under this suite's direct-controller-invocation convention). The
 * "belonging to another parent" tests below assert both the `HttpException`
 * type AND its 404 status code, so the assertion can't be satisfied by an
 * unrelated exception.
 *
 * VALIDATION-IN-CORE-TESTS: as documented at length in `RelationStoreTest`,
 * `arqel-dev/form`'s `FieldRulesExtractor` is not on core's own autoload
 * path, so `rulesFromFields()` always returns `[]` here and
 * `$request->validate([])` can never populate `$validated`. `update()`'s
 * "updates a related record" test therefore cannot assert on `body`
 * actually changing (that would only pass because validation silently
 * no-ops, exactly what must not be shipped as a green test) — it instead
 * asserts the scoped-lookup + authorize + no-exception happy path, mirroring
 * `RelationStoreTest`'s "stores a child record" test asserting existence
 * rather than field content.
 */
beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RelPostResource::class);

    Illuminate\Support\Facades\Route::get('/{resource}/{parent}/relations/{relation}/{related}/edit', fn () => 'ok')
        ->name('arqel.resources.relations.edit');
    Illuminate\Support\Facades\Route::put('/{resource}/{parent}/relations/{relation}/{related}', fn () => 'ok')
        ->name('arqel.resources.relations.update');
    Illuminate\Support\Facades\Route::delete('/{resource}/{parent}/relations/{relation}/{related}', fn () => 'ok')
        ->name('arqel.resources.relations.destroy');

    // update()/destroy() now redirect explicitly to the parent's edit page
    // (rather than the ambiguous back(), which resolved to the dashboard in
    // the real app — see RelationController). That requires
    // `arqel.resources.edit` to be a resolvable route name; register a bare
    // closure route mirroring the other relation route names above.
    Illuminate\Support\Facades\Route::get('/{resource}/{id}/edit', fn () => 'ok')
        ->name('arqel.resources.edit');
});

it('serves the edit field schema + record for a related record scoped to its parent', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $comment = RelComment::create(['post_id' => $post->id, 'body' => 'old']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.edit', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $comment->id,
    ]));

    $response = $controller->edit($request, 'rel-posts', $post->id, 'comments', $comment->id);
    $payload = $response->getData(true);

    expect($payload['fields'])->toHaveCount(1)
        ->and($payload['fields'][0]['name'])->toBe('body')
        ->and($payload['record']['body'])->toBe('old');
});

it('updates a related record scoped to its parent', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $comment = RelComment::create(['post_id' => $post->id, 'body' => 'old']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.update', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $comment->id,
    ]), 'PUT', ['body' => 'new']);

    // See VALIDATION-IN-CORE-TESTS above: with no FieldRulesExtractor on
    // core's own autoload path, `$validated` is always `[]`, so `body`
    // cannot be asserted to have changed here without faking a green test.
    // This exercises the real scoped-lookup + authorize + update() dispatch
    // path without throwing — the anti-IDOR / 404 tests below prove the
    // scoping itself.
    $controller->update($request, 'rel-posts', $post->id, 'comments', $comment->id);

    expect($comment->fresh())->not->toBeNull();
});

it('404s when updating a related record belonging to another parent', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $other = RelPost::create(['title' => 'B']);
    $foreign = RelComment::create(['post_id' => $other->id, 'body' => 'x']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.update', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $foreign->id,
    ]), 'PUT', ['body' => 'hack']);

    try {
        $controller->update($request, 'rel-posts', $post->id, 'comments', $foreign->id);
        $this->fail('Expected an HttpException 404 for cross-parent access.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }

    // The record must be untouched — the 404 happened before any mutation.
    expect($foreign->fresh()->body)->toBe('x');
});

it('404s when the parent record does not exist', function (): void {
    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.update', [
        'resource' => 'rel-posts', 'parent' => 999999, 'relation' => 'comments', 'related' => 1,
    ]), 'PUT', ['body' => 'hack']);

    try {
        $controller->update($request, 'rel-posts', 999999, 'comments', 1);
        $this->fail('Expected an HttpException 404 for a missing parent.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }
});

it('destroys a related record scoped to its parent', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $comment = RelComment::create(['post_id' => $post->id, 'body' => 'z']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.destroy', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $comment->id,
    ]), 'DELETE');

    $controller->destroy($request, 'rel-posts', $post->id, 'comments', $comment->id);

    expect(RelComment::find($comment->id))->toBeNull();
});

it('404s when destroying a related record belonging to another parent', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $other = RelPost::create(['title' => 'B']);
    $foreign = RelComment::create(['post_id' => $other->id, 'body' => 'x']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.destroy', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $foreign->id,
    ]), 'DELETE');

    try {
        $controller->destroy($request, 'rel-posts', $post->id, 'comments', $foreign->id);
        $this->fail('Expected an HttpException 404 for cross-parent access.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(404);
    }

    // The record must survive — the 404 happened before the delete.
    expect(RelComment::find($foreign->id))->not->toBeNull();
});

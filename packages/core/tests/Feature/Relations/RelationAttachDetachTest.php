<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\RelationController;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Models\RelTag;
use Arqel\Core\Tests\Fixtures\Resources\RelPostNotableResource;
use Arqel\Core\Tests\Fixtures\Resources\RelPostResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
    $this->registry->register(RelPostNotableResource::class);

    Illuminate\Support\Facades\Route::post('/{resource}/{parent}/relations/{relation}/attach', fn () => 'ok')
        ->name('arqel.resources.relations.attach');
    Illuminate\Support\Facades\Route::delete('/{resource}/{parent}/relations/{relation}/{related}/detach', fn () => 'ok')
        ->name('arqel.resources.relations.detach');

    // attach()/detach() now redirect explicitly to the parent's edit page
    // (rather than the ambiguous back(), which resolved to the dashboard in
    // the real app — see RelationController). That requires
    // `arqel.resources.edit` to be a resolvable route name; register a bare
    // closure route mirroring the other relation route names above.
    Illuminate\Support\Facades\Route::get('/{resource}/{id}/edit', fn () => 'ok')
        ->name('arqel.resources.edit');
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

/**
 * FIX 1 (mass-assignment on pivot data): the default TagsRelationManager
 * declares no pivotFields() override (allowlist is empty), so ANY
 * client-supplied pivot data must be dropped before attach() — including
 * columns that exist on the pivot table (`role`, `approved`) which an
 * attacker could otherwise abuse to grant themselves elevated pivot state.
 */
it('drops non-allowlisted pivot columns on attach (no pivotFields() override)', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $tag = RelTag::create(['name' => 'php']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.attach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'tags',
    ]), 'POST', [
        'related' => $tag->id,
        'pivot' => ['role' => 'admin', 'approved' => true, 'anything' => 'x'],
    ]);

    $controller->attach($request, 'rel-posts', $post->id, 'tags');

    // Read the pivot row directly (not via the relation's default pivot
    // select, which may simply omit unselected columns) so a NULL here
    // unambiguously means "never persisted," not "never loaded."
    $row = Illuminate\Support\Facades\DB::table('rel_post_tag')
        ->where('post_id', $post->id)->where('tag_id', $tag->id)->first();

    expect($row->role)->toBeNull()
        ->and($row->approved)->toBeNull();
});

/**
 * FIX 1 allow-path: NotableTagsRelationManager declares `pivotFields()
 * === ['note']`, so a `note` value in the request IS persisted, while a
 * non-allowlisted key alongside it is still dropped.
 */
it('persists an allowlisted pivot column while still dropping non-allowlisted keys', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $tag = RelTag::create(['name' => 'php']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.attach', [
        'resource' => 'rel-post-notables', 'parent' => $post->id, 'relation' => 'tags',
    ]), 'POST', [
        'related' => $tag->id,
        'pivot' => ['note' => 'great tag', 'role' => 'admin'],
    ]);

    $controller->attach($request, 'rel-post-notables', $post->id, 'tags');

    // The `tags()` relation's default pivot select does not include the
    // extra `note`/`role` columns (no `withPivot()` declared on the
    // fixture model), so we read the pivot row directly to observe what
    // was actually persisted.
    $row = Illuminate\Support\Facades\DB::table('rel_post_tag')
        ->where('post_id', $post->id)->where('tag_id', $tag->id)->first();

    expect($row->note)->toBe('great tag')
        ->and($row->role)->toBeNull();
});

/**
 * FIX 2 (record-level authz on detach): a record-level Gate rule ('locked'
 * tags may never be detached) must actually be consulted with the SPECIFIC
 * related record, not just the related CLASS — otherwise this per-record
 * rule is unreachable and every tag detaches regardless of its state.
 */
it('enforces a record-level detach policy against the specific related record', function (): void {
    // The user parameter must be nullable (or default null) for Laravel's
    // Gate to invoke this callback for a guest request — this suite invokes
    // controllers directly with no authenticated user, matching how the
    // rest of RelationController's tests exercise its two-tier Gate/Policy
    // fail-open semantics.
    Gate::define('detach', fn (?object $user, RelTag $tag): bool => $tag->name !== 'locked');

    $post = RelPost::create(['title' => 'A']);
    $lockedTag = RelTag::create(['name' => 'locked']);
    $openTag = RelTag::create(['name' => 'open']);
    $post->tags()->attach([$lockedTag->id, $openTag->id]);

    $controller = app(RelationController::class);

    $lockedRequest = Request::create(route('arqel.resources.relations.detach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'tags', 'related' => $lockedTag->id,
    ]), 'DELETE');

    try {
        $controller->detach($lockedRequest, 'rel-posts', $post->id, 'tags', $lockedTag->id);
        $this->fail('Expected an HttpException 403 for detaching a locked tag.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    $openRequest = Request::create(route('arqel.resources.relations.detach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'tags', 'related' => $openTag->id,
    ]), 'DELETE');

    $controller->detach($openRequest, 'rel-posts', $post->id, 'tags', $openTag->id);

    expect($post->tags()->whereKey($lockedTag->id)->exists())->toBeTrue()
        ->and($post->tags()->whereKey($openTag->id)->exists())->toBeFalse();
});

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

it('redacts a canSee(fn => false)-gated column from the relation index payload', function (): void {
    // Review finding I1: RelationController::index() used to return raw
    // $related->get()->toArray(), bypassing InertiaDataBuilder's per-record
    // canSee redaction (#182) entirely — a column that would be stripped on
    // the equivalent resource index leaked through on the relation index.
    // CommentsRelationManager::table() declares a `secret` column whose
    // duck-typed `isVisibleFor()` always returns false (the `canSee(fn () =>
    // false)` equivalent) and a `body` column that stays visible.
    $post = RelPost::create(['title' => 'A']);
    RelComment::create(['post_id' => $post->id, 'body' => 'mine', 'secret' => 'top-secret']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.index', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]));

    $response = $controller->index($request, 'rel-posts', $post->id, 'comments');
    $payload = $response->getData(true);

    expect($payload['records'])->not->toBeEmpty();

    $record = $payload['records'][0];

    // The redacted column's key must be entirely absent from the payload —
    // not merely null — so the value never reaches the client.
    expect($record)->not->toHaveKey('secret');

    // A normal (non-redacted) column's value must still come through, so
    // this isn't over-redaction / a regression on plain attributes.
    expect($record)->toHaveKey('body')
        ->and($record['body'])->toBe('mine');
});

it('serializes the relation table columns with a name key, not empty {} objects', function (): void {
    // Regression test for the E2E-diagnosed bug: RelationController::index()
    // used to return $manager->table()->toArray()['columns'] raw —
    // unserialized Column objects that JSON-encode to `{}` (no name/label)
    // and crash the React <DataTable> (col.name undefined). Fixed via
    // InertiaDataBuilder::serializeTableSchema(), the same pipeline
    // RelationManager::toArray() now uses.
    $post = RelPost::create(['title' => 'A']);
    RelComment::create(['post_id' => $post->id, 'body' => 'mine', 'secret' => 'top-secret']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.index', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]));

    $response = $controller->index($request, 'rel-posts', $post->id, 'comments');
    $payload = $response->getData(true);

    $columns = $payload['table']['columns'];

    expect($columns)->toBeArray()->not->toBeEmpty();

    foreach ($columns as $column) {
        expect($column)->toBeArray()
            ->and($column)->toHaveKey('name')
            ->and($column['name'])->not->toBeNull()
            ->and($column['name'])->not->toBe('');
    }

    expect(array_column($columns, 'name'))->toContain('body')->toContain('secret');
});

it('404s for a relation not in the resource allowlist', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.index', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'unknownrel',
    ]));

    $controller->index($request, 'rel-posts', $post->id, 'unknownrel');
})->throws(HttpException::class);

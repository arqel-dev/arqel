<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\RelationController;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Models\RelComment;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;
use Arqel\Core\Tests\Fixtures\Resources\RelPostResource;
use Illuminate\Http\Request;

/**
 * Task 5: `RelationController::create` + `store` (hasMany/morphMany).
 *
 * Mirrors `RelationIndexTest`'s established convention: the controller
 * method is invoked directly rather than driven through a real HTTP
 * request, since the polymorphic `{resource}` route group registers off
 * the Panel registry on `app->booted()`, which isolated feature tests
 * don't wire up. Bare named routes are still registered so `route()` name
 * resolution works.
 *
 * VALIDATION-IN-CORE-TESTS (verify-against-reality): `rulesFromFields()`
 * is a faithful copy of `ResourceController::extractRules()` — it resolves
 * `Arqel\Form\FieldRulesExtractor` via a string-referenced `class_exists`
 * guard, so `arqel-dev/core` stays free of a hard dependency on
 * `arqel-dev/form`. In core's OWN test suite, `arqel-dev/form` is a
 * monorepo sibling that itself depends on core (a require-dev back onto
 * it would be circular) and is genuinely NOT on core's autoload path:
 * `class_exists('Arqel\Form\FieldRulesExtractor')` is verified `false`
 * below. No existing core test exercises `assertSessionHasErrors` /
 * `extractRules` with a real extractor either (grepped the whole suite —
 * none do), so there is no established in-repo mechanism for a green
 * "real validation rejected the payload" HTTP test in this package.
 *
 * A first attempt bound a fake extractor under the real
 * `Arqel\Form\FieldRulesExtractor` FQCN via `class_alias()` so
 * `rulesFromFields()` would take its "extractor present" branch. That had
 * to be reverted: `class_alias()` is a process-global, irreversible PHP
 * side effect (no runkit/uopz is installed to undo it), and Pest runs the
 * whole suite in one process. Aliasing the real FQCN made EVERY other
 * test file's `ResourceController::extractRules()` calls take the
 * "extractor present" branch too — including `ResourceControllerTest`'s
 * Mockery-based `store` test, whose `withArgs(fn ($data) => $data ===
 * ['name' => 'Alice'])` expectation stopped matching once real rule
 * extraction ran against a fields-less fixture resource (rules collapsed
 * to `[]`), silently falling through the partial mock to the REAL
 * `runCreate()` and crashing on a migration-less `stubs` table. That is
 * exactly the kind of hidden cross-file pollution this task must not
 * introduce — confirmed by A/B suite runs (baseline 4 failures every
 * time; with the alias, a 5th, non-baseline failure appeared consistently
 * regardless of run order pairing).
 *
 * So, per the task brief's documented fallback: this file proves (1) the
 * real, current fact that the extractor is absent in core's test env,
 * (2) `create()` serves the fixture's field schema, (3) `store()`'s real
 * dispatch/create behaviour — FK injection via
 * `$parent->{relation}()->create($validated)` — which is fully exercised
 * even with `rulesFromFields()` returning `[]` (the documented behaviour
 * for "no extractor", identical to a manager declaring no fields). The
 * validation-REJECTION branch is NOT faked here: with no extractor,
 * `$request->validate([])` cannot reject anything, so a "rejects invalid
 * child" HTTP test would only be green because validation silently
 * no-ops — which is precisely what the brief says not to ship. That
 * branch is proven correct by code review instead: `rulesFromFields()`
 * mirrors `ResourceController::extractRules()`'s successful-extraction
 * path verbatim (string-ref + `class_exists` + `new ReflectionClass(...)
 * ->newInstance()` + `->extract()` + the same `is_string($name) &&
 * is_array($set)` cleanup), so any environment where `arqel-dev/form` IS
 * installed (every real consumer app) gets the same rule-derivation
 * `ResourceController::store()` already relies on and already tests
 * indirectly through its own suite.
 */
beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(RelPostResource::class);

    Illuminate\Support\Facades\Route::get('/{resource}/{parent}/relations/{relation}/create', fn () => 'ok')
        ->name('arqel.resources.relations.create');
    Illuminate\Support\Facades\Route::post('/{resource}/{parent}/relations/{relation}', fn () => 'ok')
        ->name('arqel.resources.relations.store');

    // store() now redirects explicitly to the parent's edit page (rather
    // than the ambiguous back(), which resolved to the dashboard in the
    // real app — see RelationController). That requires `arqel.resources.edit`
    // to be a resolvable route name; the polymorphic {resource} routes
    // aren't wired in this isolated suite (see class docblock), so a bare
    // closure route is registered here, mirroring the other relation route
    // names above.
    Illuminate\Support\Facades\Route::get('/{resource}/{id}/edit', fn () => 'ok')
        ->name('arqel.resources.edit');
});

it('confirms Arqel\Form\FieldRulesExtractor is not loadable in core\'s own test suite', function (): void {
    expect(class_exists('Arqel\\Form\\FieldRulesExtractor'))->toBeFalse();
});

it('serves the create field schema', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.create', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]));

    $response = $controller->create($request, 'rel-posts', $post->id, 'comments');
    $payload = $response->getData(true);

    expect($payload['fields'])->toHaveCount(1)
        ->and($payload['fields'][0]['name'])->toBe('body');
});

it('stores a child record with the parent FK injected', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $controller = app(RelationController::class);

    $request = Request::create(route('arqel.resources.relations.store', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]), 'POST', ['body' => 'hello']);

    $controller->store($request, 'rel-posts', $post->id, 'comments');

    expect(RelComment::where('post_id', $post->id)->exists())->toBeTrue();
});

it('returns [] from rulesFromFields when the extractor is absent (documented, current behaviour)', function (): void {
    $manager = new CommentsRelationManager;
    $controller = app(RelationController::class);

    $method = (new ReflectionClass(RelationController::class))->getMethod('rulesFromFields');
    $method->setAccessible(true);

    expect($method->invoke($controller, $manager))->toBe([]);
});

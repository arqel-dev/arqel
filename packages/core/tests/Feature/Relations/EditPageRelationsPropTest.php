<?php

declare(strict_types=1);

use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Arqel\Core\Tests\Fixtures\Resources\RelPostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * Task 8: the Resource edit-page Inertia payload must ship a `relations`
 * prop — the array of each declared RelationManager's `toArray($record,
 * $user)` — so the React edit page can render the relation-manager tabs.
 *
 * Mirrors InertiaDataBuilderTest's established convention (direct
 * `buildEditData()` invocation, asserted against the returned array) since
 * the polymorphic `{resource}` route group is not wired up in isolated
 * feature tests (see RelationStoreTest's documented rationale).
 */
beforeEach(function (): void {
    $this->builder = app(InertiaDataBuilder::class);
});

it('includes serialized relations in the edit page props', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $data = $this->builder->buildEditData(new RelPostResource, $post, new Request);

    expect($data)->toHaveKey('relations')
        ->and($data['relations'])->toBeArray();

    expect(collect($data['relations'])->pluck('slug')->all())->toContain('comments', 'tags');
});

it('yields an empty relations array for a Resource without relation managers (zero regression)', function (): void {
    /** @var Stub $record */
    $record = new Stub;
    $record->forceFill(['id' => 1]);

    $data = $this->builder->buildEditData(new UserResource, $record, new Request);

    expect($data)->toHaveKey('relations')
        ->and($data['relations'])->toBe([]);
});

<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Fields\Tests\Fixtures\Resources\OwningResource;
use Illuminate\Http\Request;

/**
 * #203 — `BelongsToField`'s async picker (`BelongsToInput.tsx`) is
 * driven entirely by `props.searchRoute`: it short-circuits to an
 * empty result set when that URL is absent. The route + controller
 * (`arqel.fields.search`) exist, but nothing ever fed the URL to the
 * client, so the searchable picker rendered permanently empty.
 *
 * The serializer now injects `props.searchRoute` for searchable
 * BelongsTo fields (the owning Resource slug + field name resolve the
 * `arqel.fields.search` route). These tests materialise the
 * create/edit form payloads through `InertiaDataBuilder` (the real
 * controller path) and assert the URL handoff.
 */
beforeEach(function (): void {
    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $registry->register(OwningResource::class);
});

it('injects searchRoute into a searchable BelongsToField on create', function (): void {
    $payload = app(InertiaDataBuilder::class)->buildCreateData(new OwningResource, new Request);

    $owner = collect($payload['fields'])->firstWhere('name', 'owner_id');

    expect($owner)->not->toBeNull()
        ->and($owner['props'])->toHaveKey('searchRoute')
        ->and($owner['props']['searchRoute'])->toBe(
            route('arqel.fields.search', ['resource' => 'owning-resources', 'field' => 'owner_id']),
        );
});

it('does not inject searchRoute when the BelongsToField has search disabled', function (): void {
    $payload = app(InertiaDataBuilder::class)->buildCreateData(new OwningResource, new Request);

    $inactive = collect($payload['fields'])->firstWhere('name', 'inactive_owner');

    expect($inactive)->not->toBeNull()
        ->and($inactive['props']['searchRoute'] ?? null)->toBeNull();
});

it('does not add a searchRoute to plain non-relationship fields', function (): void {
    $payload = app(InertiaDataBuilder::class)->buildCreateData(new OwningResource, new Request);

    $name = collect($payload['fields'])->firstWhere('name', 'name');

    expect($name)->not->toBeNull()
        ->and($name['props']['searchRoute'] ?? null)->toBeNull();
});

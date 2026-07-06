<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;
use Illuminate\Support\Facades\Gate;

it('serializes slug, label, type, table schema and abilities', function (): void {
    $array = (new CommentsRelationManager)->toArray(new RelPost, null);

    expect($array['slug'])->toBe('comments')
        ->and($array['label'])->toBe('Comments')
        ->and($array['type'])->toBe('hasMany')
        ->and($array['table'])->toBeArray()
        ->and($array['fields'])->toBeArray()
        ->and($array['abilities'])->toHaveKeys(['create', 'update', 'delete', 'attach', 'detach']);
});

it('fails open on abilities when no policy is registered', function (): void {
    $abilities = (new CommentsRelationManager)->abilities(new RelPost, null);

    expect($abilities['create'])->toBeTrue()
        ->and($abilities['update'])->toBeTrue();
});

it('never grants attach/detach for a non-belongsToMany relation', function (): void {
    $abilities = (new CommentsRelationManager)->abilities(new RelPost, null);

    expect($abilities['attach'])->toBeFalse()
        ->and($abilities['detach'])->toBeFalse();
});

it('denies abilities when a closure gate (no Policy) rejects, matching ResourceController two-tier semantics', function (): void {
    Gate::define('delete', fn (): bool => false);

    $abilities = (new CommentsRelationManager)->abilities(new RelPost, null);

    expect($abilities['delete'])->toBeFalse();
});

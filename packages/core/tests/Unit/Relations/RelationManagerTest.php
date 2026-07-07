<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;

it('derives a slug from the relationship name', function (): void {
    expect((new CommentsRelationManager)->slug())->toBe('comments');
});

it('detects hasMany relation type from the parent', function (): void {
    $type = (new CommentsRelationManager)->relationType(new RelPost);

    expect($type)->toBe('hasMany')
        ->and((new CommentsRelationManager)->supportsAttach(new RelPost))->toBeFalse();
});

it('exposes a table object and a null form by default', function (): void {
    $manager = new CommentsRelationManager;

    // Duck-typed: core does not depend on arqel-dev/table, so we assert the
    // shape (a toArray()-able object), not an instanceof Table.
    expect($manager->table())->toBeObject()
        ->and(method_exists($manager->table(), 'toArray'))->toBeTrue()
        ->and($manager->form())->toBeNull();
});

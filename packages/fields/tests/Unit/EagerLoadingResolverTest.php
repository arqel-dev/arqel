<?php

declare(strict_types=1);

use Arqel\Fields\EagerLoadingResolver;
use Arqel\Fields\Tests\Fixtures\OtherStubResource;
use Arqel\Fields\Tests\Fixtures\StubResource;
use Arqel\Fields\Types\BelongsToField;
use Arqel\Fields\Types\HasManyField;
use Arqel\Fields\Types\TextField;

it('returns an empty list when no relational fields are present', function (): void {
    $relations = EagerLoadingResolver::resolve([
        new TextField('name'),
        new TextField('email'),
    ]);

    expect($relations)->toBe([]);
});

it('extracts the relation name from BelongsToField', function (): void {
    $relations = EagerLoadingResolver::resolve([
        BelongsToField::make('author_id', StubResource::class),
        new TextField('title'),
    ]);

    expect($relations)->toBe(['author']);
});

it('extracts the relation name from HasManyField', function (): void {
    $relations = EagerLoadingResolver::resolve([
        HasManyField::make('posts', OtherStubResource::class),
    ]);

    expect($relations)->toBe(['posts']);
});

it('combines BelongsTo and HasMany relations and dedupes', function (): void {
    $relations = EagerLoadingResolver::resolve([
        BelongsToField::make('author_id', StubResource::class),
        BelongsToField::make('editor_id', StubResource::class),
        HasManyField::make('comments', OtherStubResource::class),
        BelongsToField::make('author_id', StubResource::class), // duplicate
    ]);

    expect($relations)->toBe(['author', 'editor', 'comments']);
});

it('honours an explicit relationship() override on BelongsToField', function (): void {
    $field = BelongsToField::make('author_id', StubResource::class)
        ->relationship('writer');

    $relations = EagerLoadingResolver::resolve([$field]);

    expect($relations)->toBe(['writer']);
});

it('honours an explicit relationship() override on HasManyField', function (): void {
    $field = HasManyField::make('posts', OtherStubResource::class)
        ->relationship('articles');

    $relations = EagerLoadingResolver::resolve([$field]);

    expect($relations)->toBe(['articles']);
});

it('skips non-Field entries without throwing', function (): void {
    $relations = EagerLoadingResolver::resolve([
        new TextField('name'),
        'not a field',
        null,
        BelongsToField::make('author_id', StubResource::class),
    ]);

    expect($relations)->toBe(['author']);
});

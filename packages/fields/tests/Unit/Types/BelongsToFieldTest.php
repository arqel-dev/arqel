<?php

declare(strict_types=1);

use Arqel\Fields\Tests\Fixtures\OtherStubResource;
use Arqel\Fields\Tests\Fixtures\StubResource;
use Arqel\Fields\Types\BelongsToField;
use Arqel\Fields\Types\HasManyField;

it('builds BelongsToField via factory and exposes related Resource metadata', function (): void {
    $field = BelongsToField::make('author_id', StubResource::class);

    expect($field->getType())->toBe('belongsTo')
        ->and($field->getComponent())->toBe('BelongsToInput')
        ->and($field->getRelatedResource())->toBe(StubResource::class)
        ->and($field->getRelationshipName())->toBe('author')
        ->and($field->isSearchable())->toBeTrue()
        ->and($field->isPreloadEnabled())->toBeFalse();
});

it('rejects related Resource classes that do not implement HasResource', function (): void {
    BelongsToField::make('author_id', stdClass::class);
})->throws(InvalidArgumentException::class, 'must implement');

it('uses the field name verbatim when it does not end in _id', function (): void {
    $field = BelongsToField::make('owner', StubResource::class);

    expect($field->getRelationshipName())->toBe('owner');
});

it('toggles searchable, preload, and stores searchColumns + optionLabel', function (): void {
    $callback = fn ($u) => $u->name;
    $field = BelongsToField::make('author_id', StubResource::class)
        ->searchable(false)
        ->preload()
        ->searchColumns(['name', 'email'])
        ->optionLabel($callback);

    expect($field->isSearchable())->toBeFalse()
        ->and($field->isPreloadEnabled())->toBeTrue()
        ->and($field->getSearchColumns())->toBe(['name', 'email'])
        ->and($field->getOptionLabel())->toBe($callback);
});

it('overrides the relationship name and stores a relation query', function (): void {
    $query = fn ($q) => $q;
    $field = BelongsToField::make('author_id', StubResource::class)
        ->relationship('writer', $query);

    expect($field->getRelationshipName())->toBe('writer')
        ->and($field->getRelationQuery())->toBe($query);
});

it('serialises BelongsTo metadata into type-specific props', function (): void {
    $props = BelongsToField::make('author_id', StubResource::class)
        ->preload()
        ->searchColumns(['name'])
        ->getTypeSpecificProps();

    expect($props)->toBe([
        'relatedResource' => StubResource::class,
        'relationship' => 'author',
        'searchable' => true,
        'searchColumns' => ['name'],
        'preload' => true,
    ]);
});

it('builds HasManyField via factory', function (): void {
    $field = HasManyField::make('posts', OtherStubResource::class);

    expect($field->getType())->toBe('hasMany')
        ->and($field->getComponent())->toBe('HasManyTable')
        ->and($field->getRelatedResource())->toBe(OtherStubResource::class)
        ->and($field->getRelationshipName())->toBe('posts');
});

it('rejects HasMany related Resource classes that do not implement HasResource', function (): void {
    HasManyField::make('posts', stdClass::class);
})->throws(InvalidArgumentException::class, 'must implement');

it('accepts canAdd/canEdit forward-compat flags and overrides relationship', function (): void {
    $field = HasManyField::make('posts', OtherStubResource::class)
        ->relationship('articles')
        ->canAddRecords()
        ->canEditRecords();

    expect($field->getRelationshipName())->toBe('articles')
        ->and($field->getTypeSpecificProps())->toBe([
            'relatedResource' => OtherStubResource::class,
            'relationship' => 'articles',
            'canAdd' => true,
            'canEdit' => true,
        ]);
});

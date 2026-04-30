<?php

declare(strict_types=1);

use Arqel\Fields\FieldFactory;
use Arqel\FieldsAdvanced\Types\TagsField;

it('exposes the correct type and component for TagsField', function (): void {
    $field = new TagsField('categories');

    expect($field->getType())->toBe('tags')
        ->and($field->getComponent())->toBe('TagsInput');
});

it('can be constructed via TagsField::make()', function (): void {
    $field = TagsField::make('categories');

    expect($field)->toBeInstanceOf(TagsField::class)
        ->and($field->getName())->toBe('categories');
});

it('can be constructed via the FieldFactory tags macro', function (): void {
    $field = FieldFactory::tags('categories');

    expect($field)->toBeInstanceOf(TagsField::class)
        ->and($field->getName())->toBe('categories');
});

it('ships the canonical default state', function (): void {
    $props = (new TagsField('categories'))->getTypeSpecificProps();

    expect($props)->toBe([
        'suggestions' => [],
        'creatable' => true,
        'maxTags' => null,
        'separator' => ',',
        'unique' => true,
    ]);
});

it('persists suggestions via suggestions() and is fluent, filtering non-strings', function (): void {
    $field = (new TagsField('categories'))
        ->suggestions(['php', 'laravel', 42, null, ['nested'], 'react']);

    expect($field)->toBeInstanceOf(TagsField::class)
        ->and($field->getTypeSpecificProps()['suggestions'])->toBe(['php', 'laravel', 'react']);
});

it('resolves a Closure-based suggestions list lazily inside getTypeSpecificProps()', function (): void {
    $calls = 0;
    $field = (new TagsField('categories'))->suggestions(function () use (&$calls): array {
        $calls++;

        return ['php', 'js'];
    });

    expect($calls)->toBe(0);

    $first = $field->getTypeSpecificProps()['suggestions'];
    $second = $field->getTypeSpecificProps()['suggestions'];

    expect($first)->toBe(['php', 'js'])
        ->and($second)->toBe(['php', 'js'])
        ->and($calls)->toBe(2);
});

it('falls back to an empty list when the suggestions Closure returns a non-array', function (): void {
    $field = (new TagsField('categories'))->suggestions(fn (): string => 'not array');

    expect($field->getTypeSpecificProps()['suggestions'])->toBe([]);
});

it('toggles creatable via creatable() with default true and explicit reset', function (): void {
    $defaultOn = new TagsField('categories');
    $explicitOff = (new TagsField('categories'))->creatable(false);
    $reEnabled = (new TagsField('categories'))->creatable(false)->creatable();

    expect($defaultOn->getTypeSpecificProps()['creatable'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['creatable'])->toBeFalse()
        ->and($reEnabled->getTypeSpecificProps()['creatable'])->toBeTrue();
});

it('clamps maxTags to a sensible floor and accepts null reset', function (): void {
    $five = (new TagsField('categories'))->maxTags(5);
    $clampedZero = (new TagsField('categories'))->maxTags(0);
    $clampedNeg = (new TagsField('categories'))->maxTags(-3);
    $reset = (new TagsField('categories'))->maxTags(5)->maxTags(null);

    expect($five->getTypeSpecificProps()['maxTags'])->toBe(5)
        ->and($clampedZero->getTypeSpecificProps()['maxTags'])->toBe(1)
        ->and($clampedNeg->getTypeSpecificProps()['maxTags'])->toBe(1)
        ->and($reset->getTypeSpecificProps()['maxTags'])->toBeNull();
});

it('persists separator via separator() and is fluent', function (): void {
    $field = (new TagsField('categories'))->separator(';');

    expect($field)->toBeInstanceOf(TagsField::class)
        ->and($field->getTypeSpecificProps()['separator'])->toBe(';');
});

it('rejects an empty separator with InvalidArgumentException', function (): void {
    (new TagsField('categories'))->separator('');
})->throws(InvalidArgumentException::class);

it('toggles unique via uniqueTags() with default true and explicit reset', function (): void {
    $defaultOn = new TagsField('categories');
    $explicitOff = (new TagsField('categories'))->uniqueTags(false);
    $reEnabled = (new TagsField('categories'))->uniqueTags(false)->uniqueTags();

    expect($defaultOn->getTypeSpecificProps()['unique'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['unique'])->toBeFalse()
        ->and($reEnabled->getTypeSpecificProps()['unique'])->toBeTrue();
});

it('returns all 5 keys from getTypeSpecificProps()', function (): void {
    $props = (new TagsField('categories'))->getTypeSpecificProps();

    expect(array_keys($props))->toBe([
        'suggestions',
        'creatable',
        'maxTags',
        'separator',
        'unique',
    ]);
});

it('serialises the full type-specific props payload end-to-end', function (): void {
    $field = (new TagsField('categories'))
        ->suggestions(['php', 'laravel', 'react'])
        ->creatable(false)
        ->maxTags(10)
        ->separator('|')
        ->uniqueTags(false);

    expect($field->getTypeSpecificProps())->toBe([
        'suggestions' => ['php', 'laravel', 'react'],
        'creatable' => false,
        'maxTags' => 10,
        'separator' => '|',
        'unique' => false,
    ])
        ->and($field->getName())->toBe('categories')
        ->and($field->getType())->toBe('tags')
        ->and($field->getComponent())->toBe('TagsInput');
});

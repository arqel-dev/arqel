<?php

declare(strict_types=1);

use Arqel\Fields\Types\ColorField;
use Arqel\Fields\Types\HiddenField;
use Arqel\Fields\Types\SlugField;

it('exposes the correct type, component, and defaults for ColorField', function (): void {
    $field = new ColorField('brand_color');

    expect($field->getType())->toBe('color')
        ->and($field->getComponent())->toBe('ColorInput')
        ->and($field->getFormat())->toBe(ColorField::FORMAT_HEX)
        ->and($field->allowsAlpha())->toBeFalse()
        ->and($field->getPresets())->toBe([])
        ->and($field->getDefaultRules())->toBe(['string']);
});

it('serialises ColorField presets, format, and alpha when configured', function (): void {
    $field = (new ColorField('brand_color'))
        ->presets(['#FF0000', '#00FF00'])
        ->format(ColorField::FORMAT_RGB)
        ->alpha();

    expect($field->getTypeSpecificProps())->toBe([
        'presets' => ['#FF0000', '#00FF00'],
        'format' => 'rgb',
        'alpha' => true,
    ]);
});

it('exposes the correct type and component for HiddenField', function (): void {
    $field = new HiddenField('team_id');

    expect($field->getType())->toBe('hidden')
        ->and($field->getComponent())->toBe('HiddenInput');
});

it('SlugField stays equivalent to FIELDS-004 defaults', function (): void {
    $field = (new SlugField('slug'))->fromField('title');

    expect($field->getType())->toBe('slug')
        ->and($field->getComponent())->toBe('SlugInput')
        ->and($field->getTypeSpecificProps())->toBe([
            'fromField' => 'title',
            'separator' => '-',
        ]);
});

it('records reservedSlugs and emits a not_in rule', function (): void {
    $field = (new SlugField('slug'))
        ->fromField('title')
        ->reservedSlugs(['admin', 'api']);

    expect($field->getReservedSlugs())->toBe(['admin', 'api'])
        ->and($field->getDefaultRules())->toBe([
            'string',
            'not_in:admin,api',
        ])
        ->and($field->getTypeSpecificProps()['reservedSlugs'])->toBe(['admin', 'api']);
});

it('records unique target and emits a unique rule against the resolved table', function (): void {
    $modelClass = new class
    {
        public function getTable(): string
        {
            return 'posts';
        }
    };

    $field = (new SlugField('slug'))->uniqueIn($modelClass::class);

    expect($field->getUniqueModel())->toBe($modelClass::class)
        ->and($field->getUniqueColumn())->toBe('slug')
        ->and($field->getDefaultRules())->toContain('unique:posts,slug');
});

it('honours an explicit unique column override', function (): void {
    $modelClass = new class
    {
        public function getTable(): string
        {
            return 'articles';
        }
    };

    $field = (new SlugField('slug'))->uniqueIn($modelClass::class, 'permalink');

    expect($field->getUniqueColumn())->toBe('permalink')
        ->and($field->getDefaultRules())->toContain('unique:articles,permalink');
});

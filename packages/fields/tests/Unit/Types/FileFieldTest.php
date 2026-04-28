<?php

declare(strict_types=1);

use Arqel\Fields\Types\FileField;
use Arqel\Fields\Types\ImageField;

it('exposes the correct type and component for FileField', function (): void {
    $field = new FileField('document');

    expect($field->getType())->toBe('file')
        ->and($field->getComponent())->toBe('FileInput')
        ->and($field->getDisk())->toBe('local')
        ->and($field->getVisibility())->toBe(FileField::VISIBILITY_PRIVATE)
        ->and($field->getStrategy())->toBe(FileField::STRATEGY_DIRECT)
        ->and($field->isMultiple())->toBeFalse()
        ->and($field->isReorderable())->toBeFalse();
});

it('serialises FileField storage props with defaults', function (): void {
    $props = (new FileField('doc'))->getTypeSpecificProps();

    expect($props)->toBe([
        'disk' => 'local',
        'visibility' => 'private',
        'multiple' => false,
        'reorderable' => false,
        'strategy' => 'direct',
    ]);
});

it('honours disk/directory/visibility/maxSize/mimeTypes overrides', function (): void {
    $field = (new FileField('doc'))
        ->disk('s3')
        ->directory('uploads/users')
        ->visibility(FileField::VISIBILITY_PUBLIC)
        ->maxSize(5120)
        ->acceptedFileTypes(['application/pdf', 'image/png']);

    $props = $field->getTypeSpecificProps();

    expect($props)->toBe([
        'disk' => 's3',
        'directory' => 'uploads/users',
        'visibility' => 'public',
        'maxSize' => 5120,
        'acceptedFileTypes' => ['application/pdf', 'image/png'],
        'multiple' => false,
        'reorderable' => false,
        'strategy' => 'direct',
    ]);
});

it('produces single-file Laravel rules including mimetypes and max size', function (): void {
    $field = (new FileField('doc'))
        ->maxSize(2048)
        ->acceptedFileTypes(['application/pdf']);

    expect($field->getDefaultRules())->toBe([
        'file',
        'max:2048',
        'mimetypes:application/pdf',
    ]);
});

it('produces array Laravel rule when multiple is enabled', function (): void {
    $field = (new FileField('docs'))->multiple()->maxSize(2048);

    expect($field->isMultiple())->toBeTrue()
        ->and($field->getDefaultRules())->toBe(['array']);
});

it('forces multiple on when reorderable is enabled', function (): void {
    $field = (new FileField('docs'))->reorderable();

    expect($field->isReorderable())->toBeTrue()
        ->and($field->isMultiple())->toBeTrue();
});

it('switches the upload strategy to spatie or presigned', function (): void {
    expect((new FileField('d'))->using(FileField::STRATEGY_SPATIE_MEDIA_LIBRARY)->getStrategy())
        ->toBe('spatie-media-library')
        ->and((new FileField('d'))->using(FileField::STRATEGY_PRESIGNED)->getStrategy())
        ->toBe('presigned');
});

it('exposes ImageField defaults including image mime gate', function (): void {
    $field = new ImageField('avatar');

    expect($field->getType())->toBe('image')
        ->and($field->getComponent())->toBe('ImageInput')
        ->and($field->getAcceptedFileTypes())->toBe(['image/jpeg', 'image/png', 'image/webp'])
        ->and($field->getDefaultRules())->toBe(['image']);
});

it('serialises ImageField crop and resize hints when set', function (): void {
    $field = (new ImageField('avatar'))
        ->imageCropAspectRatio('1:1')
        ->imageResizeTargetWidth(512);

    $props = $field->getTypeSpecificProps();

    expect($props['imageCropAspectRatio'])->toBe('1:1')
        ->and($props['imageResizeTargetWidth'])->toBe(512);
});

it('omits ImageField crop/resize hints when not configured', function (): void {
    $props = (new ImageField('avatar'))->getTypeSpecificProps();

    expect($props)->not->toHaveKey('imageCropAspectRatio')
        ->and($props)->not->toHaveKey('imageResizeTargetWidth');
});

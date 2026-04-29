<?php

declare(strict_types=1);

use Arqel\FieldsAdvanced\Types\RichTextField;

it('exposes the correct type and component for RichTextField', function (): void {
    $field = new RichTextField('content');

    expect($field->getType())->toBe('richText')
        ->and($field->getComponent())->toBe('RichTextInput');
});

it('ships the canonical default toolbar', function (): void {
    $field = new RichTextField('content');

    expect($field->getTypeSpecificProps()['toolbar'])->toBe([
        'bold',
        'italic',
        'link',
        'bulletList',
        'orderedList',
        'heading',
        'blockquote',
    ]);
});

it('replaces the toolbar via toolbar() and silently drops non-strings', function (): void {
    $field = (new RichTextField('content'))
        ->toolbar(['bold', 42, 'italic', null, 'underline', ['nested']]);

    expect($field->getTypeSpecificProps()['toolbar'])->toBe(['bold', 'italic', 'underline']);
});

it('clamps maxLength to a minimum of 1', function (): void {
    $zero = (new RichTextField('content'))->maxLength(0);
    $negative = (new RichTextField('content'))->maxLength(-50);
    $valid = (new RichTextField('content'))->maxLength(2048);

    expect($zero->getTypeSpecificProps()['maxLength'])->toBe(1)
        ->and($negative->getTypeSpecificProps()['maxLength'])->toBe(1)
        ->and($valid->getTypeSpecificProps()['maxLength'])->toBe(2048);
});

it('defaults maxLength to 65535 when never set', function (): void {
    expect((new RichTextField('content'))->getTypeSpecificProps()['maxLength'])->toBe(65535);
});

it('passes imageUploadDirectory through verbatim', function (): void {
    $field = (new RichTextField('content'))
        ->imageUploadDisk('s3')
        ->imageUploadDirectory('posts/images');

    $props = $field->getTypeSpecificProps();

    expect($props['imageUploadDirectory'])->toBe('posts/images')
        ->and($props['imageUploadRoute'])->toBe('/arqel/fields/upload?disk=s3');
});

it('returns null imageUploadRoute when no disk is configured', function (): void {
    $field = new RichTextField('content');

    expect($field->getTypeSpecificProps()['imageUploadRoute'])->toBeNull()
        ->and($field->getTypeSpecificProps()['imageUploadDirectory'])->toBeNull();
});

it('toggles fileAttachments via fileAttachments()', function (): void {
    $defaultOff = new RichTextField('content');
    $on = (new RichTextField('content'))->fileAttachments();
    $explicitOff = (new RichTextField('content'))->fileAttachments(false);

    expect($defaultOff->getTypeSpecificProps()['fileAttachments'])->toBeFalse()
        ->and($on->getTypeSpecificProps()['fileAttachments'])->toBeTrue()
        ->and($explicitOff->getTypeSpecificProps()['fileAttachments'])->toBeFalse();
});

it('filters non-string entries from customMarks', function (): void {
    $field = (new RichTextField('content'))
        ->customMarks(['highlight', 123, 'subscript', null, ['nested'], 'superscript']);

    expect($field->getTypeSpecificProps()['customMarks'])
        ->toBe(['highlight', 'subscript', 'superscript']);
});

it('filters mentionable entries that are missing id or name', function (): void {
    $field = (new RichTextField('content'))->mentionable([
        ['id' => 1, 'name' => 'Alice', 'avatar' => '/a.png'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3], // missing name
        ['name' => 'Carol'], // missing id
        'not-an-array',
        ['id' => 4, 'name' => 42], // non-string name
        ['id' => ['nested'], 'name' => 'Dave'], // bad id type
        ['id' => 5, 'name' => 'Eve', 'avatar' => 99], // bad avatar dropped, entry kept
    ]);

    expect($field->getTypeSpecificProps()['mentionable'])->toBe([
        ['id' => 1, 'name' => 'Alice', 'avatar' => '/a.png'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 5, 'name' => 'Eve'],
    ]);
});

it('serialises the full type-specific props payload end-to-end', function (): void {
    $field = (new RichTextField('content'))
        ->toolbar(['bold', 'italic'])
        ->imageUploadDisk('public')
        ->imageUploadDirectory('uploads')
        ->maxLength(1000)
        ->fileAttachments()
        ->customMarks(['highlight'])
        ->mentionable([
            ['id' => 1, 'name' => 'Alice'],
        ]);

    expect($field->getTypeSpecificProps())->toBe([
        'toolbar' => ['bold', 'italic'],
        'imageUploadRoute' => '/arqel/fields/upload?disk=public',
        'imageUploadDirectory' => 'uploads',
        'maxLength' => 1000,
        'fileAttachments' => true,
        'customMarks' => ['highlight'],
        'mentionable' => [
            ['id' => 1, 'name' => 'Alice'],
        ],
    ]);
});

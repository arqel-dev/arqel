<?php

declare(strict_types=1);

use Arqel\Fields\Types\FileField;
use Arqel\Fields\Types\ImageField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/*
 * #166-A — a multiple FileField/ImageField must emit per-element
 * `{name}.*` rules (file/image + max + mimetypes) so the configured
 * maxSize/mimetypes are not silently dropped for the create/update
 * payload, mirroring how SelectField emits `{name}.* => in:…`.
 */

it('emits array + nested *.file/max/mimetypes rules for a multiple FileField', function (): void {
    $field = (new FileField('docs'))
        ->multiple()
        ->maxSize(1024)
        ->acceptedFileTypes(['application/pdf']);

    expect($field->getDefaultRules())->toBe(['array'])
        ->and($field->getNestedValidationRules())->toBe([
            'docs.*' => ['nullable', 'file', 'max:1024', 'mimetypes:application/pdf'],
        ]);
});

it('emits image-gated nested rules for a multiple ImageField', function (): void {
    $field = (new ImageField('gallery'))
        ->multiple()
        ->maxSize(2048);

    expect($field->getNestedValidationRules())->toBe([
        'gallery.*' => ['nullable', 'image', 'max:2048', 'mimetypes:image/jpeg,image/png,image/webp'],
    ]);
});

it('emits no nested rules for a single (non-multiple) FileField', function (): void {
    $field = (new FileField('doc'))->maxSize(1024)->acceptedFileTypes(['application/pdf']);

    expect($field->getNestedValidationRules())->toBe([]);
});

it('validates each element of a multiple ImageField payload', function (): void {
    $field = (new ImageField('gallery'))->multiple();

    $rules = ['gallery' => $field->getValidationRules()] + $field->getNestedValidationRules();

    $image = UploadedFile::fake()->image('ok.jpg');
    $notImage = UploadedFile::fake()->create('evil.pdf', 10, 'application/pdf');

    expect(Validator::make(['gallery' => [$image, $notImage]], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['gallery' => [$image]], $rules)->passes())->toBeTrue();
});

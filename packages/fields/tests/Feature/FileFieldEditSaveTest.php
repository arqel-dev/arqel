<?php

declare(strict_types=1);

use Arqel\Fields\Types\FileField;
use Arqel\Fields\Types\ImageField;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

/**
 * Regression guard for issue #150 — editing a record with a populated
 * FileField/ImageField (no re-upload) returned 422.
 *
 * On EDIT the frontend re-submits the record's stored attribute — the
 * path STRING (e.g. `avatars/photo.png`). The unconditional `file`/`image`
 * rule rejects any non-`UploadedFile`, and `nullable` does not skip a
 * non-null string, so saving without re-uploading 422'd. The rule must now
 * accept a stored-path string while still enforcing the upload checks
 * (file/image/size/mime) for an actual `UploadedFile`.
 */
it('lets an optional FileField accept a stored-path string on edit (#150)', function (): void {
    $field = new FileField('avatar');

    $rules = ['avatar' => $field->getValidationRules()];

    expect(Validator::make(['avatar' => 'avatars/existing-photo.png'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['avatar' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make([], $rules)->passes())->toBeTrue();
});

it('still enforces the file check for an actual upload on a FileField (#150)', function (): void {
    $field = new FileField('avatar');

    $rules = ['avatar' => $field->getValidationRules()];

    $upload = UploadedFile::fake()->create('doc.pdf', 10);

    expect(Validator::make(['avatar' => $upload], $rules)->passes())->toBeTrue()
        // A non-string, non-upload scalar (e.g. an int) is neither a stored
        // path nor a real file — it must still be rejected.
        ->and(Validator::make(['avatar' => 12345], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['avatar' => ['not', 'a', 'file']], $rules)->fails())->toBeTrue();
});

it('still enforces max size and mime gate for an actual upload (#150)', function (): void {
    $field = (new FileField('doc'))
        ->maxSize(100) // 100 KB
        ->acceptedFileTypes(['application/pdf']);

    $rules = ['doc' => $field->getValidationRules()];

    $tooLarge = UploadedFile::fake()->create('big.pdf', 500, 'application/pdf'); // 500 KB
    $wrongMime = UploadedFile::fake()->create('img.png', 10, 'image/png');
    $ok = UploadedFile::fake()->create('fine.pdf', 10, 'application/pdf');

    expect(Validator::make(['doc' => $tooLarge], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['doc' => $wrongMime], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['doc' => $ok], $rules)->passes())->toBeTrue()
        // The stored path still slips past size/mime — it is not an upload.
        ->and(Validator::make(['doc' => 'docs/report.pdf'], $rules)->passes())->toBeTrue();
});

it('lets an optional ImageField accept a stored-path string but still gates real images (#150)', function (): void {
    $field = new ImageField('photo');

    $rules = ['photo' => $field->getValidationRules()];

    $realImage = UploadedFile::fake()->image('photo.jpg');
    $notImage = UploadedFile::fake()->create('notes.txt', 5, 'text/plain');

    expect(Validator::make(['photo' => 'photos/portrait.jpg'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['photo' => null], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['photo' => $realImage], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['photo' => $notImage], $rules)->fails())->toBeTrue();
});

it('a required FileField still rejects an empty value on create (#150)', function (): void {
    $field = (new FileField('avatar'))->required();

    $rules = ['avatar' => $field->getValidationRules()];

    expect(Validator::make([], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['avatar' => null], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['avatar' => ''], $rules)->fails())->toBeTrue()
        // But a populated stored path on edit of a required field is fine.
        ->and(Validator::make(['avatar' => 'avatars/photo.png'], $rules)->passes())->toBeTrue()
        // And a real upload satisfies required + file.
        ->and(Validator::make(['avatar' => UploadedFile::fake()->create('a.pdf', 5)], $rules)->passes())->toBeTrue();
});

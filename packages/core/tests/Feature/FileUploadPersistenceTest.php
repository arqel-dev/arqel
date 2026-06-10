<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Uploaded-file persistence on the write pipeline (#245).
 *
 * The stock ImageInput/FileInput submit the raw `File` via the main
 * form's multipart body, so a file/image field's value reaches
 * `runCreate`/`runUpdate` as an `UploadedFile`. Before the fix the
 * pipeline `fill()`ed the column with the upload object (cast to its
 * temp path, which vanishes) and never stored the file to disk.
 *
 * The fix detects an upload-capable field generically (duck-typed
 * `storeUploadedFile()` marker so core stays decoupled from
 * `arqel-dev/fields`) and, when the submitted value is an
 * `UploadedFile`, stores it to the field's configured disk/directory
 * and replaces `$data[$name]` with the returned relative path. A
 * string value (unchanged stored path on edit) or null is left
 * untouched so the edit round-trip never wipes the existing file.
 */

/**
 * Duck-typed stand-in for `FileField`: exposes the same
 * `getName()` + `storeUploadedFile()` contract the pipeline consumes,
 * so this test fixes the core pipeline without a hard dependency on
 * `arqel-dev/fields`. Storage logic mirrors the real field.
 */
final class UploadCapableField
{
    public function __construct(
        private readonly string $name,
        private readonly string $disk = 'public',
        private readonly ?string $directory = null,
        private readonly string $visibility = 'public',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function storeUploadedFile(UploadedFile $file): string
    {
        $stored = $file->store($this->directory ?? '', [
            'disk' => $this->disk,
            'visibility' => $this->visibility,
        ]);

        return is_string($stored) ? $stored : '';
    }
}

final class FileUploadResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'file-uploads';

    public function fields(): array
    {
        return [
            new UploadCapableField('name', disk: 'public'),
            new UploadCapableField('avatar', disk: 'public', directory: 'avatars'),
        ];
    }
}

beforeEach(function (): void {
    Storage::fake('public');

    Schema::create('stubs', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
        $table->string('avatar')->nullable();
        $table->timestamps();
    });
});

it('store: stores the uploaded file and persists the relative path, not the temp path', function (): void {
    $resource = new FileUploadResource;
    $file = UploadedFile::fake()->image('avatar.png', 10, 10);

    $record = $resource->runCreate([
        'name' => 'Alice',
        'avatar' => $file,
    ]);

    // (a) the file is written to the configured disk/directory.
    Storage::disk('public')->assertExists($record->avatar);

    // (b) the column holds the relative stored path, never the upload's
    // temp path nor the UploadedFile object.
    expect($record->avatar)
        ->toBeString()
        ->toStartWith('avatars/')
        ->not->toBe($file->getPathname());
});

it('update: keeps the existing stored path when no new file is uploaded', function (): void {
    $resource = new FileUploadResource;

    $record = Stub::query()->create([
        'name' => 'Bob',
        'avatar' => 'avatars/existing.png',
    ]);

    // The frontend re-submits the stored path STRING when the populated
    // field is edited without re-uploading.
    $resource->runUpdate($record, [
        'name' => 'Bob Updated',
        'avatar' => 'avatars/existing.png',
    ]);

    $record->refresh();

    expect($record->name)->toBe('Bob Updated')
        ->and($record->avatar)->toBe('avatars/existing.png');
});

it('update: replaces the stored path when a new file is uploaded', function (): void {
    $resource = new FileUploadResource;

    $record = Stub::query()->create([
        'name' => 'Carol',
        'avatar' => 'avatars/old.png',
    ]);

    $file = UploadedFile::fake()->image('new.png', 10, 10);

    $resource->runUpdate($record, [
        'name' => 'Carol',
        'avatar' => $file,
    ]);

    $record->refresh();

    Storage::disk('public')->assertExists($record->avatar);
    expect($record->avatar)
        ->toStartWith('avatars/')
        ->not->toBe('avatars/old.png');
});

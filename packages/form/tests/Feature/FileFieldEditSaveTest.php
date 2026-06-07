<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Fields\Types\FileField;
use Arqel\Fields\Types\TextField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Integration regression for issue #150 — editing a record with a populated
 * FileField (no re-upload) returned 422.
 *
 * The full controller path is exercised (core + form + fields booted, real
 * FieldRulesExtractor): the frontend re-submits the record's stored attribute
 * (the path STRING) on update. Before the fix the `file` rule rejected the
 * string and `ResourceController::update` -> `validated()` 422'd; saving any
 * record with a populated file field without re-uploading was impossible.
 */
final class FileEditModel extends Model
{
    protected $table = 'file_edit_records';

    protected $guarded = [];

    public $timestamps = false;
}

final class FileEditResource extends Resource
{
    public static string $model = FileEditModel::class;

    public static ?string $slug = 'file-edit';

    public function fields(): array
    {
        return [
            (new TextField('title'))->required(),
            new FileField('avatar'), // optional
        ];
    }
}

beforeEach(function (): void {
    Storage::fake('local');

    Schema::create('file_edit_records', function ($table): void {
        $table->increments('id');
        $table->string('title');
        $table->string('avatar')->nullable();
    });

    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(FileEditResource::class);

    $this->builder = app(InertiaDataBuilder::class);

    // Stub the redirect target so redirect()->route() resolves.
    Illuminate\Support\Facades\Route::get('/{resource}/{id}/edit', fn () => 'ok')
        ->name('arqel.resources.edit');
});

afterEach(function (): void {
    Schema::dropIfExists('file_edit_records');
});

it('updating a record that re-submits its stored file path returns 200, not 422 (#150)', function (): void {
    $record = FileEditModel::query()->create([
        'title' => 'Original',
        'avatar' => 'avatars/existing-photo.png',
    ]);

    $controller = new ResourceController($this->registry, $this->builder);

    // Frontend re-submits the unchanged stored path string + a changed field.
    $request = Illuminate\Http\Request::create('/file-edit/'.$record->getKey(), 'PUT', [
        'title' => 'Updated',
        'avatar' => 'avatars/existing-photo.png',
    ]);

    $response = $controller->update($request, 'file-edit', (string) $record->getKey());

    expect($response->getStatusCode())->toBe(302); // redirect, not a 422

    $record->refresh();

    expect($record->title)->toBe('Updated')
        // The stored path is preserved, not nulled and not corrupted.
        ->and($record->avatar)->toBe('avatars/existing-photo.png');
});

it('updating with a brand-new upload validates and stores the file (#150)', function (): void {
    $record = FileEditModel::query()->create([
        'title' => 'Original',
        'avatar' => 'avatars/old.png',
    ]);

    $controller = new ResourceController($this->registry, $this->builder);

    $upload = UploadedFile::fake()->create('new.png', 20, 'image/png');

    $request = Illuminate\Http\Request::create('/file-edit/'.$record->getKey(), 'PUT', [
        'title' => 'Original',
    ]);
    $request->files->set('avatar', $upload);

    $response = $controller->update($request, 'file-edit', (string) $record->getKey());

    expect($response->getStatusCode())->toBe(302);

    // The real upload passed validation (no 422); the controller reached
    // runUpdate. (Direct-upload fields normally persist the path via the
    // separate FieldUploadController; here we only assert validation accepted
    // the actual file rather than rejecting it.)
    $record->refresh();
    expect($record->title)->toBe('Original');
});

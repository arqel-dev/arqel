<?php

declare(strict_types=1);

use Arqel\Import\Jobs\ProcessImportJob;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

/**
 * Both import routes run behind `web + auth`; an unauthenticated request
 * redirects to the (undefined, in this package's test app) `login` route
 * rather than exercising the controller. Authenticate a stub user so the
 * tests actually reach `ImportUploadController`.
 */
function authedImportUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);

    return $user;
}

it('accepts a CSV upload and dispatches the import job', function (): void {
    Queue::fake();

    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $response = $this->actingAs(authedImportUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ]);

    $response->assertRedirect();
    Queue::assertPushed(ProcessImportJob::class);
});

it('rejects an importer class that does not extend Importer without instantiating or dispatching', function (): void {
    Queue::fake();
    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $response = $this->actingAs(authedImportUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => stdClass::class,
    ]);

    $response->assertStatus(422);
    Queue::assertNothingPushed();
});

it('rejects an unsupported file extension', function (): void {
    Queue::fake();
    $file = UploadedFile::fake()->create('data.pdf', 10);

    $this->actingAs(authedImportUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ])->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});

it('rejects a .txt file at validation instead of 500ing on the format enum', function (): void {
    // `txt` used to be allowed by the `mimes` rule, but ImportFormat only
    // knows csv/xlsx — `fromExtension('txt')` throws, which surfaced as an
    // unhandled HTTP 500. It must be rejected as a validation error instead.
    Queue::fake();
    $file = UploadedFile::fake()->createWithContent('data.txt', "name,email\nAda,ada@example.com\n");

    $this->actingAs(authedImportUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ])->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});

it('stores the upload at the exact path handed to the job', function (): void {
    // Round-trip guard: the controller must write the file to the same
    // absolute path it passes to the job as `sourcePath`. A `storeAs()` +
    // `storage_path('app/...')` mismatch (Laravel 11/12 moved the local disk
    // root to storage/app/private) left the job with a non-existent path.
    // Faking the queue lets us inspect the dispatched job's sourcePath while
    // the real file write still happens.
    Queue::fake();
    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $this->actingAs(authedImportUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ])->assertRedirect();

    Queue::assertPushed(ProcessImportJob::class, function (ProcessImportJob $job): bool {
        expect(file_exists($job->sourcePath))->toBeTrue();

        @unlink($job->sourcePath);

        return true;
    });
});

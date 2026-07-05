<?php

declare(strict_types=1);

use Arqel\Import\Jobs\ProcessImportJob;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Arqel\Import\Tests\Fixtures\Models\TestUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;

function importTestUser(): TestUser
{
    /** @var TestUser $user */
    $user = TestUser::query()->create(['name' => 'importer']);

    return $user;
}

it('accepts a CSV upload and dispatches the import job for an authorized user', function (): void {
    Queue::fake();
    Gate::define('import', fn (): bool => true);

    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $response = $this->actingAs(importTestUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ]);

    $response->assertRedirect();
    Queue::assertPushed(ProcessImportJob::class, function (ProcessImportJob $job): bool {
        return $job->importerClass === StubUserImporter::class
            && $job->sourcePath !== ''
            && $job->failedRowsDir === null;
    });
});

it('allows the upload when no import ability/policy is registered (scaffold mode)', function (): void {
    Queue::fake();

    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $response = $this->actingAs(importTestUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ]);

    $response->assertRedirect();
    Queue::assertPushed(ProcessImportJob::class);
});

it('rejects the upload with 403 when the import ability denies the user', function (): void {
    Queue::fake();
    Gate::define('import', fn (): bool => false);

    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $this->actingAs(importTestUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ])->assertForbidden();

    Queue::assertNothingPushed();
});

it('redirects a guest to login instead of accepting the upload', function (): void {
    Queue::fake();

    // Register a stub `login` route so the `auth` middleware has
    // somewhere to redirect to instead of throwing a RouteNotFound.
    Illuminate\Support\Facades\Route::get('/login', fn () => 'login')->name('login');

    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $this->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ])->assertRedirect();

    Queue::assertNothingPushed();
});

it('rejects an unsupported file extension', function (): void {
    Queue::fake();
    $file = UploadedFile::fake()->create('data.pdf', 10);

    $this->actingAs(importTestUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ])->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});

it('rejects a missing importer class', function (): void {
    Queue::fake();
    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $this->actingAs(importTestUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
    ])->assertSessionHasErrors('importer');

    Queue::assertNothingPushed();
});

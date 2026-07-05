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

it('rejects an unsupported file extension', function (): void {
    Queue::fake();
    $file = UploadedFile::fake()->create('data.pdf', 10);

    $this->actingAs(authedImportUser())->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ])->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});

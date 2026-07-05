<?php

declare(strict_types=1);

use Arqel\Import\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Facades\Gate;

function failedRowsTestUser(): TestUser
{
    /** @var TestUser $user */
    $user = TestUser::query()->create(['name' => 'downloader']);

    return $user;
}

function putFailedRowsFixture(string $importId): void
{
    $dir = storage_path('app/arqel-imports');
    if (! is_dir($dir)) {
        mkdir($dir, 0o755, true);
    }

    file_put_contents($dir.'/failed-'.$importId.'.csv', "name,email,_errors\nBad,bad,The email is invalid\n");
}

afterEach(function (): void {
    $dir = storage_path('app/arqel-imports');
    foreach (glob($dir.'/failed-*.csv') ?: [] as $file) {
        @unlink($file);
    }
});

it('returns the failed-rows CSV for an import id when the import ability allows it', function (): void {
    Gate::define('import', fn (): bool => true);

    $importId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    putFailedRowsFixture($importId);

    $this->actingAs(failedRowsTestUser())
        ->get(route('arqel.imports.failed-rows', ['importId' => $importId]))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=failed-'.$importId.'.csv');
});

it('allows the download when no import ability/policy is registered (scaffold mode)', function (): void {
    $importId = 'bbbbbbbb-bbbb-cccc-dddd-eeeeeeeeeeee';
    putFailedRowsFixture($importId);

    $this->actingAs(failedRowsTestUser())
        ->get(route('arqel.imports.failed-rows', ['importId' => $importId]))
        ->assertOk();
});

it('rejects the download with 403 when the import ability denies the user', function (): void {
    Gate::define('import', fn (): bool => false);

    $importId = 'cccccccc-bbbb-cccc-dddd-eeeeeeeeeeee';
    putFailedRowsFixture($importId);

    $this->actingAs(failedRowsTestUser())
        ->get(route('arqel.imports.failed-rows', ['importId' => $importId]))
        ->assertForbidden();
});

it('redirects a guest to login instead of serving the file', function (): void {
    // Register a stub `login` route so the `auth` middleware has
    // somewhere to redirect to instead of throwing a RouteNotFound.
    Illuminate\Support\Facades\Route::get('/login', fn () => 'login')->name('login');

    $importId = 'dddddddd-bbbb-cccc-dddd-eeeeeeeeeeee';
    putFailedRowsFixture($importId);

    $this->get(route('arqel.imports.failed-rows', ['importId' => $importId]))
        ->assertRedirect();
});

it('404s for an unknown import id', function (): void {
    $this->actingAs(failedRowsTestUser())
        ->get(route('arqel.imports.failed-rows', ['importId' => 'ffffffff-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

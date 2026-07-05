<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Both import routes run behind `web + auth`; an unauthenticated request
 * redirects to the (undefined, in this package's test app) `login` route
 * rather than exercising the controller. Authenticate a stub user so the
 * tests actually reach `FailedRowsDownloadController`.
 */
function authedImportDownloadUser(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com']);

    return $user;
}

it('returns the failed-rows CSV for an import id', function (): void {
    $dir = storage_path('app/arqel-imports');
    if (! is_dir($dir)) {
        mkdir($dir, 0o755, true);
    }
    $importId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    file_put_contents($dir.'/failed-'.$importId.'.csv', "name,email,_errors\nBad,bad,The email is invalid\n");

    $this->actingAs(authedImportDownloadUser())
        ->get(route('arqel.imports.failed-rows', ['importId' => $importId]))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=failed-'.$importId.'.csv');
});

it('404s for an unknown import id', function (): void {
    $this->actingAs(authedImportDownloadUser())
        ->get(route('arqel.imports.failed-rows', ['importId' => 'ffffffff-0000-0000-0000-000000000000']))
        ->assertNotFound();
});

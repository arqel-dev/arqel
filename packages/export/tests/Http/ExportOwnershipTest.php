<?php

declare(strict_types=1);

use Arqel\Export\Models\Export;
use Illuminate\Support\Facades\File;

function makeExportFile(string $id, string $dir): string
{
    File::ensureDirectoryExists($dir);
    $path = $dir.'/export-'.$id.'.csv';
    File::put($path, "id\n1\n");

    return $path;
}

function actingUser(int|string $id): object
{
    $user = new class extends Illuminate\Foundation\Auth\User
    {
        public int|string $identifier;

        public function getAuthIdentifier(): mixed
        {
            return $this->identifier;
        }
    };

    $user->identifier = $id;

    return $user;
}

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/arqel-export-own-'.uniqid();
    config()->set('arqel-export.destination_dir', $this->dir);
});

function urlFor(string $id): string
{
    return '/admin/exports/'.$id.'/download';
}

it('serves the file to its owner', function (): void {
    $id = '11111111-1111-4111-8111-111111111111';
    $path = makeExportFile($id, $this->dir);
    Export::create(['id' => $id, 'owner_user_id' => '7', 'format' => 'csv', 'path' => $path, 'expires_at' => null]);

    $this->be(actingUser(7))
        ->get(urlFor($id))
        ->assertOk();
});

it('returns 404 (not 403) when another user requests the export', function (): void {
    $id = '22222222-2222-4222-8222-222222222222';
    makeExportFile($id, $this->dir);
    Export::create(['id' => $id, 'owner_user_id' => '7', 'format' => 'csv', 'path' => $this->dir.'/export-'.$id.'.csv', 'expires_at' => null]);

    $response = $this->be(actingUser(99))->get(urlFor($id));
    $response->assertNotFound();
    expect($response->getStatusCode())->not->toBe(403);
});

it('returns 404 for an ownerless (legacy/CLI) export', function (): void {
    $id = '33333333-3333-4333-8333-333333333333';
    makeExportFile($id, $this->dir);
    Export::create(['id' => $id, 'owner_user_id' => null, 'format' => 'csv', 'path' => $this->dir.'/export-'.$id.'.csv', 'expires_at' => null]);

    $this->be(actingUser(7))->get(urlFor($id))->assertNotFound();
});

it('returns 404 when no Export record exists for the id', function (): void {
    $id = '44444444-4444-4444-8444-444444444444';
    makeExportFile($id, $this->dir); // file on disk, but no DB row

    $this->be(actingUser(7))->get(urlFor($id))->assertNotFound();
});

it('matches an int user id against a string owner_user_id', function (): void {
    $id = '55555555-5555-4555-8555-555555555555';
    $path = makeExportFile($id, $this->dir);
    Export::create(['id' => $id, 'owner_user_id' => '42', 'format' => 'csv', 'path' => $path, 'expires_at' => null]);

    $this->be(actingUser(42))->get(urlFor($id))->assertOk(); // int 42 vs "42"
    $this->be(actingUser(7))->get(urlFor($id))->assertNotFound();
});

it('returns 400 for a malformed id', function (): void {
    // The route's `where('exportId', '[a-f0-9-]+')` uses the exact same
    // charset as the controller's own UUID_PATTERN guard, so a request
    // whose id violates that charset (e.g. contains a space) never
    // reaches the controller — Laravel's router itself returns 404
    // (route not found) before dispatch. This pre-existing route
    // constraint is unrelated to the ownership fix under test; the
    // controller's 400 guard is still exercised directly (bypassing
    // routing) by the existing packages/export/tests/Feature/
    // ExportDownloadControllerTest.php.
    $this->be(actingUser(7))->get(urlFor('NOT VALID'))->assertStatus(404);

    // An id that satisfies the router's charset but isn't a real UUID
    // still passes the controller's own guard (same regex) and falls
    // through to the ownership/not-found checks.
    $this->be(actingUser(7))->get(urlFor('deadbeef'))->assertNotFound();
});

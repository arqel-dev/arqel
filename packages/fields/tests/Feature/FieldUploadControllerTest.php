<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Http\Controllers\FieldUploadController;
use Arqel\Fields\Tests\Fixtures\Resources\FormOnlyUploadingResource;
use Arqel\Fields\Tests\Fixtures\Resources\UploadingResource;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Storage::fake('local');

    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(UploadingResource::class);
    $this->registry->register(FormOnlyUploadingResource::class);

    $this->controller = new FieldUploadController($this->registry);
});

/**
 * Build an upload request carrying an authenticated user so the
 * controller's authorization layer has a subject to gate against.
 *
 * @param array<string, mixed> $params
 */
function uploadRequestAs(string $method = 'POST', array $params = []): Request
{
    $user = new class extends Authenticatable {};
    $user->forceFill(['id' => 1]);

    $request = Request::create('/upload', $method, $params);
    $request->setUserResolver(fn () => $user);
    auth()->setUser($user);

    return $request;
}

it('store: validates and writes the upload to the configured disk', function (): void {
    $file = UploadedFile::fake()->create('avatar.png', 100, 'image/png');

    $request = Request::create('/upload', 'POST');
    $request->files->set('file', $file);

    $response = $this->controller->store($request, 'uploading-resources', 'avatar');
    $payload = $response->getData(true);

    expect($payload)->toHaveKeys(['path', 'size', 'originalName'])
        ->and($payload['originalName'])->toBe('avatar.png');

    Storage::disk('local')->assertExists($payload['path']);
});

it('store: persists the field\'s public visibility on a private-default disk (#142)', function (): void {
    // Storage::fake defaults to private visibility; a visibility('public')
    // field must override that so url() works on e.g. an s3 disk. Before the
    // fix the store() call dropped the field's visibility and the object kept
    // the disk default (private), so this assertion failed.
    $file = UploadedFile::fake()->create('public.png', 100, 'image/png');

    $request = Request::create('/upload', 'POST');
    $request->files->set('file', $file);

    $payload = $this->controller->store($request, 'uploading-resources', 'public_avatar')->getData(true);

    expect(Storage::disk('local')->getVisibility($payload['path']))->toBe('public');
});

it('store: persists the field\'s private visibility (#142)', function (): void {
    $file = UploadedFile::fake()->create('private.png', 100, 'image/png');

    $request = Request::create('/upload', 'POST');
    $request->files->set('file', $file);

    $payload = $this->controller->store($request, 'uploading-resources', 'private_avatar')->getData(true);

    expect(Storage::disk('local')->getVisibility($payload['path']))->toBe('private');
});

it('store: rejects when the file is missing', function (): void {
    $this->controller->store(Request::create('/upload', 'POST'), 'uploading-resources', 'avatar');
})->throws(Illuminate\Validation\ValidationException::class);

it('store: 404 when the resource slug is unknown', function (): void {
    $this->controller->store(Request::create('/upload', 'POST'), 'unknown', 'avatar');
})->throws(HttpException::class);

it('store: 400 when the field is not a FileField', function (): void {
    $request = Request::create('/upload', 'POST');
    $request->files->set('file', UploadedFile::fake()->create('a.png'));

    $this->controller->store($request, 'uploading-resources', 'name');
})->throws(HttpException::class);

it('destroy: removes a stored file', function (): void {
    $file = UploadedFile::fake()->create('to-delete.png', 50);
    $request = Request::create('/upload', 'POST');
    $request->files->set('file', $file);
    $payload = $this->controller->store($request, 'uploading-resources', 'avatar')->getData(true);

    Storage::disk('local')->assertExists($payload['path']);

    $deleteRequest = Request::create('/upload', 'DELETE', ['path' => $payload['path']]);
    $response = $this->controller->destroy($deleteRequest, 'uploading-resources', 'avatar');

    expect($response->getData(true))->toBe(['deleted' => true]);
    Storage::disk('local')->assertMissing($payload['path']);
});

it('destroy: 422 when path is missing', function (): void {
    $this->controller->destroy(Request::create('/upload', 'DELETE'), 'uploading-resources', 'avatar');
})->throws(HttpException::class);

it('store: resolves a FileField declared only inside form() (#94)', function (): void {
    $file = UploadedFile::fake()->create('form-avatar.png', 100, 'image/png');

    $request = Request::create('/upload', 'POST');
    $request->files->set('file', $file);

    // Before the fix this 404'd because the controller iterated fields()
    // (empty here) instead of effectiveFields() (the form's field list).
    $response = $this->controller->store($request, 'form-only-uploading-resources', 'avatar');
    $payload = $response->getData(true);

    expect($payload)->toHaveKeys(['path', 'size', 'originalName'])
        ->and($payload['originalName'])->toBe('form-avatar.png');

    Storage::disk('local')->assertExists($payload['path']);
});

/*
 * #166-B — the HTTP upload path must honour ImageField's `image`
 * rule, not just a hard-coded `file` + the mimetypes whitelist. With
 * `acceptedFileTypes([])` the mimetypes gate is cleared, so only the
 * `image` rule (which decodes the file) can reject a non-image. Before
 * the fix the controller hard-coded `['required','file']` and accepted
 * a fake non-image as an "image" upload.
 */

it('store: ImageField with cleared mimes rejects a non-image upload (#166)', function (): void {
    $request = Request::create('/upload', 'POST');
    // A genuinely non-image payload that nonetheless passes the bare `file`
    // rule. The `image` gate must reject it.
    $request->files->set('file', UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain'));

    $this->controller->store($request, 'uploading-resources', 'photo_no_mimes');
})->throws(Illuminate\Validation\ValidationException::class);

it('store: ImageField with cleared mimes still accepts a real image (#166)', function (): void {
    $request = Request::create('/upload', 'POST');
    $request->files->set('file', UploadedFile::fake()->image('real.jpg'));

    $payload = $this->controller->store($request, 'uploading-resources', 'photo_no_mimes')->getData(true);

    expect($payload['originalName'])->toBe('real.jpg');
    Storage::disk('local')->assertExists($payload['path']);
});

/*
 * Authorization (#128) — upload/delete must honour the owner
 * resource's Policy and constrain the deleted path to the field's
 * directory. Without these guards any authenticated user could
 * write/delete arbitrary files on the resource's disk.
 */

it('store: 403 when the owner Policy denies create/update (no file written)', function (): void {
    Gate::define('create', fn () => false);
    Gate::define('update', fn () => false);

    $request = uploadRequestAs('POST');
    $request->files->set('file', UploadedFile::fake()->create('blocked.png', 100, 'image/png'));

    try {
        $this->controller->store($request, 'uploading-resources', 'avatar');
        $this->fail('Expected a 403 HttpException.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('store: allows the upload when the owner Policy permits create', function (): void {
    Gate::define('create', fn () => true);
    Gate::define('update', fn () => true);

    $request = uploadRequestAs('POST');
    $request->files->set('file', UploadedFile::fake()->create('allowed.png', 100, 'image/png'));

    $payload = $this->controller->store($request, 'uploading-resources', 'avatar')->getData(true);

    expect($payload['originalName'])->toBe('allowed.png');
    Storage::disk('local')->assertExists($payload['path']);
});

it('destroy: 403 when the owner Policy denies update/delete (file kept)', function (): void {
    // Seed a file with create allowed, then deny the delete.
    Gate::define('create', fn () => true);
    Gate::define('update', fn () => true);
    $seedReq = uploadRequestAs('POST');
    $seedReq->files->set('file', UploadedFile::fake()->create('keep.png', 50));
    $path = $this->controller->store($seedReq, 'uploading-resources', 'avatar')->getData(true)['path'];

    Gate::define('update', fn () => false);
    Gate::define('delete', fn () => false);

    try {
        $this->controller->destroy(uploadRequestAs('DELETE', ['path' => $path]), 'uploading-resources', 'avatar');
        $this->fail('Expected a 403 HttpException.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    Storage::disk('local')->assertExists($path);
});

it('destroy: rejects a path-traversal payload even for an authorized user', function (): void {
    Gate::define('update', fn () => true);
    Gate::define('delete', fn () => true);

    // A real file outside the field's directory the attacker tries to reach.
    Storage::disk('local')->put('secret.txt', 'top secret');

    try {
        $this->controller->destroy(
            uploadRequestAs('DELETE', ['path' => '../../secret.txt']),
            'uploading-resources',
            'avatar',
        );
        $this->fail('Expected the traversal path to be rejected.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBeIn([403, 422]);
    }

    Storage::disk('local')->assertExists('secret.txt');
});

it('destroy: rejects an absolute path outside the field directory', function (): void {
    Gate::define('update', fn () => true);
    Gate::define('delete', fn () => true);

    Storage::disk('local')->put('outside.txt', 'data');

    try {
        $this->controller->destroy(
            uploadRequestAs('DELETE', ['path' => '/etc/passwd']),
            'uploading-resources',
            'avatar',
        );
        $this->fail('Expected the absolute path to be rejected.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBeIn([403, 422]);
    }

    Storage::disk('local')->assertExists('outside.txt');
});

it('store + destroy: no-policy scaffold still works (baseline regression guard)', function (): void {
    // No Gate::define / Gate::policy → scaffold mode → no enforcement.
    $request = uploadRequestAs('POST');
    $request->files->set('file', UploadedFile::fake()->create('scaffold.png', 50));
    $path = $this->controller->store($request, 'uploading-resources', 'avatar')->getData(true)['path'];

    Storage::disk('local')->assertExists($path);

    $response = $this->controller->destroy(
        uploadRequestAs('DELETE', ['path' => $path]),
        'uploading-resources',
        'avatar',
    );

    expect($response->getData(true))->toBe(['deleted' => true]);
    Storage::disk('local')->assertMissing($path);
});

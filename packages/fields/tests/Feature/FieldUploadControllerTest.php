<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Http\Controllers\FieldUploadController;
use Arqel\Fields\Tests\Fixtures\Resources\UploadingResource;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Storage::fake('local');

    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(UploadingResource::class);

    $this->controller = new FieldUploadController($this->registry);
});

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

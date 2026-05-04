<?php

declare(strict_types=1);

use Arqel\Core\Http\Controllers\ResourceController;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\InertiaDataBuilder;
use Arqel\Core\Tests\Fixtures\Models\Stub;
use Arqel\Core\Tests\Fixtures\Resources\MockableResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Inertia useForm flow (FORM-008): when validation fails the
 * controller throws `ValidationException`, which Laravel's
 * exception handler converts into `back()->withErrors()->withInput()`
 * — exactly what Inertia's `useForm` consumes on the client.
 *
 * The full validation path (rules extracted from Fields) is
 * covered in `arqel-dev/form` (FieldRulesExtractor tests). Here we
 * verify the controller's success branch when rules are empty
 * (no `arqel-dev/form` extractor → permissive fallback): route + CSRF
 * params are stripped before reaching `runCreate`.
 */
beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();

    $this->builder = app(InertiaDataBuilder::class);

    Route::get('/{resource}/{id}/edit', fn () => 'ok')->name('arqel.resources.edit');
});

it('store: permissive fallback strips route + CSRF params', function (): void {
    $resource = Mockery::mock(MockableResource::class)->makePartial();
    $record = (new Stub)->forceFill(['id' => 1]);
    $resource->shouldReceive('runCreate')
        ->once()
        ->withArgs(function (array $data): bool {
            expect($data)->toMatchArray(['name' => 'Alice'])
                ->and($data)->not->toHaveKey('_token')
                ->and($data)->not->toHaveKey('resource');

            return true;
        })
        ->andReturn($record);

    app()->bind(MockableResource::class, fn () => $resource);
    $this->registry->register(MockableResource::class);

    $controller = new ResourceController($this->registry, $this->builder);

    $request = Request::create('/mocks', 'POST', [
        '_token' => 'x',
        'resource' => 'mocks',
        'name' => 'Alice',
    ]);

    $response = $controller->store($request, 'mocks');

    expect($response->getStatusCode())->toBe(302);
});

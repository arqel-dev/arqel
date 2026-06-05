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

/**
 * Fail-closed guard (FORM-007 security): `extractRules()` returns `null`
 * ONLY when the extractor class is genuinely absent (`arqel-dev/form`
 * not installed). When the class exists but is broken — cannot be
 * instantiated, lacks `extract()`, or throws — the controller raises a
 * RuntimeException instead of silently accepting unvalidated input
 * (which would permit mass assignment).
 *
 * The "broken extractor" branch is exercised in `arqel-dev/form`, where
 * a real `Arqel\Form\FieldRulesExtractor` exists and can be stubbed
 * without synthesising classes into the shared autoload (which would
 * leak into the rest of this suite). Here in core we only assert the
 * permissive-fallback contract above, since `arqel-dev/form` is not a
 * dependency of the core test bench.
 */
it('extracts validation rules from the form fields, not just fields()', function (): void {
    // A form-like object whose getFields() differs from fields().
    $form = new class
    {
        public function getFields(): array
        {
            return ['form-only-field'];
        }
    };

    $resource = Mockery::mock(MockableResource::class)->makePartial();
    $resource->shouldReceive('form')->andReturn($form);
    $resource->shouldReceive('fields')->andReturn(['flat-only-field']);

    // effectiveFields() is a concrete method on Resource; the partial mock
    // runs the real one, which must pick the form's fields — the same
    // source ResourceController::extractRules() now reads.
    expect($resource->effectiveFields())->toBe(['form-only-field']);
});

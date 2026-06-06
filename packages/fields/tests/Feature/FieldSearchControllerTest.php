<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Http\Controllers\FieldSearchController;
use Arqel\Fields\Tests\Fixtures\Models\StubModel;
use Arqel\Fields\Tests\Fixtures\Resources\FormOnlyOwningResource;
use Arqel\Fields\Tests\Fixtures\Resources\OwnerResource;
use Arqel\Fields\Tests\Fixtures\Resources\OwningResource;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Build a search request carrying an authenticated user so the
 * controller's authorization layer has a subject to gate against.
 *
 * @param array<string, mixed> $params
 */
function searchRequestAs(array $params = []): Request
{
    $user = new class extends Authenticatable {};
    $user->forceFill(['id' => 1]);

    $request = Request::create('/search', 'GET', $params);
    $request->setUserResolver(fn () => $user);
    auth()->setUser($user);

    return $request;
}

function seedStubModels(): void
{
    Schema::create('stub_models', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
    });

    StubModel::query()->insert([
        ['id' => 1, 'name' => 'Ada Lovelace'],
        ['id' => 2, 'name' => 'Grace Hopper'],
    ]);
}

beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(OwningResource::class);
    $this->registry->register(OwnerResource::class);
    $this->registry->register(FormOnlyOwningResource::class);

    $this->controller = new FieldSearchController($this->registry);

    // Bind a simple in-memory record collection through the StubModel
    // by overriding the static `query()` via container — simplest path
    // is to just verify the 4xx branches without hitting DB.
});

it('404 when the resource slug is not registered', function (): void {
    $this->controller->__invoke(new Request, 'unknown', 'owner');
})->throws(HttpException::class);

it('404 when the field name is not declared on the resource', function (): void {
    $this->controller->__invoke(new Request, 'owning-resources', 'missing');
})->throws(HttpException::class);

it('400 when the field is not a BelongsToField', function (): void {
    $this->controller->__invoke(new Request, 'owning-resources', 'name');
})->throws(HttpException::class);

it('403 when the BelongsToField has search disabled', function (): void {
    $this->controller->__invoke(new Request, 'owning-resources', 'inactive_owner');
})->throws(HttpException::class);

it('resolves a searchable BelongsToField declared only inside form() (#94)', function (): void {
    seedStubModels();

    $request = Request::create('/search', 'GET', ['q' => 'Ada']);

    // Before the fix this 404'd because the controller iterated fields()
    // (empty here) instead of effectiveFields() (the form's field list).
    $response = $this->controller->__invoke($request, 'form-only-owning-resources', 'owner_id');
    $payload = $response->getData(true);

    expect($payload)->toBeArray()
        ->and($payload)->toHaveCount(1)
        ->and($payload[0]['value'])->toBe(1);
});

/*
 * Authorization (#128) — search must honour the *related* model's
 * `viewAny` Policy. Without it any authenticated user could
 * enumerate labels/PII of related records bypassing their Policy.
 */

it('search: 403 when the related model Policy denies viewAny (no labels returned)', function (): void {
    seedStubModels();
    Gate::define('viewAny', fn () => false);

    try {
        $this->controller->__invoke(searchRequestAs(['q' => 'Ada']), 'owning-resources', 'owner_id');
        $this->fail('Expected a 403 HttpException.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

it('search: returns labels when the related model Policy permits viewAny', function (): void {
    seedStubModels();
    Gate::define('viewAny', fn () => true);

    $payload = $this->controller
        ->__invoke(searchRequestAs(['q' => 'Ada']), 'owning-resources', 'owner_id')
        ->getData(true);

    expect($payload)->toBeArray()
        ->and($payload)->toHaveCount(1)
        ->and($payload[0]['value'])->toBe(1);
});

it('search: no-policy scaffold still returns labels (baseline regression guard)', function (): void {
    seedStubModels();

    // No Gate::define('viewAny') / Gate::policy → scaffold mode.
    $payload = $this->controller
        ->__invoke(searchRequestAs(['q' => 'Ada']), 'owning-resources', 'owner_id')
        ->getData(true);

    expect($payload)->toBeArray()->and($payload)->toHaveCount(1);
});

<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Http\Controllers\FieldSearchController;
use Arqel\Fields\Tests\Fixtures\Resources\FormOnlyOwningResource;
use Arqel\Fields\Tests\Fixtures\Resources\OwnerResource;
use Arqel\Fields\Tests\Fixtures\Resources\OwningResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    Schema::create('stub_models', function ($table): void {
        $table->increments('id');
        $table->string('name')->nullable();
    });

    Arqel\Fields\Tests\Fixtures\Models\StubModel::query()->insert([
        ['id' => 1, 'name' => 'Ada Lovelace'],
        ['id' => 2, 'name' => 'Grace Hopper'],
    ]);

    $request = Request::create('/search', 'GET', ['q' => 'Ada']);

    // Before the fix this 404'd because the controller iterated fields()
    // (empty here) instead of effectiveFields() (the form's field list).
    $response = $this->controller->__invoke($request, 'form-only-owning-resources', 'owner_id');
    $payload = $response->getData(true);

    expect($payload)->toBeArray()
        ->and($payload)->toHaveCount(1)
        ->and($payload[0]['value'])->toBe(1);
});

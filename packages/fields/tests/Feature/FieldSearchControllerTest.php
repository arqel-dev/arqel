<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Http\Controllers\FieldSearchController;
use Arqel\Fields\Tests\Fixtures\Resources\OwnerResource;
use Arqel\Fields\Tests\Fixtures\Resources\OwningResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(OwningResource::class);
    $this->registry->register(OwnerResource::class);

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

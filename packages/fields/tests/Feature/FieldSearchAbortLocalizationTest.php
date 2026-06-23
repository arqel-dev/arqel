<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Fields\Http\Controllers\FieldSearchController;
use Arqel\Fields\Tests\Fixtures\Resources\OwnerResource;
use Arqel\Fields\Tests\Fixtures\Resources\OwningResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->registry = app(ResourceRegistry::class);
    $this->registry->clear();
    $this->registry->register(OwningResource::class);
    $this->registry->register(OwnerResource::class);

    $this->controller = new FieldSearchController($this->registry);

    Lang::addLines(['messages.field_search.not_searchable' => 'Field is not searchable.'], 'en', 'arqel');
    Lang::addLines(['messages.field_search.not_searchable' => 'O campo não permite busca.'], 'pt_BR', 'arqel');
    Lang::addLines(['messages.field_search.disabled' => 'Field has search disabled.'], 'en', 'arqel');
    Lang::addLines(['messages.field_search.disabled' => 'A busca está desabilitada para este campo.'], 'pt_BR', 'arqel');
});

it('localizes the not-searchable abort message via the request locale', function (): void {
    app()->setLocale('pt_BR');

    try {
        $this->controller->__invoke(new Request, 'owning-resources', 'name');
        $this->fail('Expected a 400 HttpException.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(400)
            ->and($e->getMessage())->toBe('O campo não permite busca.');
    }
});

it('localizes the search-disabled abort message via the request locale', function (): void {
    app()->setLocale('pt_BR');

    try {
        $this->controller->__invoke(new Request, 'owning-resources', 'inactive_owner');
        $this->fail('Expected a 403 HttpException.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403)
            ->and($e->getMessage())->toBe('A busca está desabilitada para este campo.');
    }
});

it('keeps the English literal for the not-searchable message under the en locale', function (): void {
    app()->setLocale('en');

    try {
        $this->controller->__invoke(new Request, 'owning-resources', 'name');
        $this->fail('Expected a 400 HttpException.');
    } catch (HttpException $e) {
        expect($e->getMessage())->toBe('Field is not searchable.');
    }
});

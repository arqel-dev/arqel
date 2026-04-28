<?php

declare(strict_types=1);

use Arqel\Nav\BreadcrumbsBuilder;
use Arqel\Nav\Tests\Fixtures\UserResource;

beforeEach(function (): void {
    $this->builder = (new BreadcrumbsBuilder)->withResources([UserResource::class]);
});

it('returns [] for an unrecognised route format', function (): void {
    expect($this->builder->buildFor('weird'))->toBe([]);
});

it('builds a single crumb for index routes', function (): void {
    $crumbs = $this->builder->buildFor('arqel.admin.users.index');

    expect($crumbs)->toHaveCount(1)
        ->and($crumbs[0])->toBe(['label' => 'Users', 'url' => null]);
});

it('builds [resource > Create] for create routes', function (): void {
    $crumbs = $this->builder->buildFor('arqel.admin.users.create');

    expect($crumbs)->toHaveCount(2)
        ->and($crumbs[0])->toBe(['label' => 'Users', 'url' => '/users'])
        ->and($crumbs[1])->toBe(['label' => 'Create', 'url' => null]);
});

it('builds [resource > id > Edit] when given a record id', function (): void {
    $crumbs = $this->builder->buildFor('arqel.admin.users.edit', ['id' => 42]);

    expect($crumbs)->toHaveCount(3)
        ->and($crumbs[0])->toBe(['label' => 'Users', 'url' => '/users'])
        ->and($crumbs[1]['label'])->toBe('42')
        ->and($crumbs[1]['url'])->toBe('/users/42')
        ->and($crumbs[2])->toBe(['label' => 'Edit', 'url' => null]);
});

it('falls back to the slug ucfirst when the resource is not registered', function (): void {
    $crumbs = (new BreadcrumbsBuilder)->buildFor('arqel.admin.posts.index');

    expect($crumbs[0]['label'])->toBe('Posts');
});

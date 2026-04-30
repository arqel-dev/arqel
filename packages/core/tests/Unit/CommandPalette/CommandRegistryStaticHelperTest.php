<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandRegistry;
use Illuminate\Contracts\Auth\Authenticatable;

beforeEach(function (): void {
    $this->registry = new CommandRegistry;
});

it('registerStatic happy path produces a Command exposed via all()', function (): void {
    $this->registry->registerStatic(
        id: 'cache-clear',
        label: 'Clear cache',
        url: '/admin/cache-clear',
    );

    $all = $this->registry->all();

    expect($all)->toHaveCount(1)
        ->and($all[0])->toBeInstanceOf(Command::class)
        ->and($all[0]->id)->toBe('cache-clear')
        ->and($all[0]->label)->toBe('Clear cache')
        ->and($all[0]->url)->toBe('/admin/cache-clear');
});

it('registerStatic throws on duplicate id', function (): void {
    $this->registry->registerStatic('dup', 'Duplicate', '/dup');

    expect(fn () => $this->registry->registerStatic('dup', 'Duplicate again', '/dup'))
        ->toThrow(InvalidArgumentException::class, "Command id 'dup' already registered");
});

it('registerStatic propagates every optional field', function (): void {
    $this->registry->registerStatic(
        id: 'full',
        label: 'Full command',
        url: '/admin/full',
        description: 'Does the full thing',
        category: 'System',
        icon: 'sparkles',
    );

    $command = $this->registry->all()[0];

    expect($command)
        ->toBeInstanceOf(Command::class)
        ->and($command->id)->toBe('full')
        ->and($command->label)->toBe('Full command')
        ->and($command->url)->toBe('/admin/full')
        ->and($command->description)->toBe('Does the full thing')
        ->and($command->category)->toBe('System')
        ->and($command->icon)->toBe('sparkles');
});

it('registerClosureProvider adds the closure as a provider', function (): void {
    $this->registry->registerClosureProvider(
        fn (?Authenticatable $user, string $query): array => [
            new Command(
                id: 'from-closure',
                label: 'From closure',
                url: '/admin/from-closure',
            ),
        ],
    );

    expect($this->registry->providers())->toHaveCount(1);

    $resolved = $this->registry->resolveFor(null, '');

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0]->id)->toBe('from-closure');
});

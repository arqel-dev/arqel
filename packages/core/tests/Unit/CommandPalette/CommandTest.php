<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;

it('toArray() emits the six canonical keys with explicit null fallbacks', function (): void {
    $command = new Command(
        id: 'users',
        label: 'Users',
        url: '/admin/users',
    );

    expect($command->toArray())->toBe([
        'id' => 'users',
        'label' => 'Users',
        'url' => '/admin/users',
        'description' => null,
        'category' => null,
        'icon' => null,
    ]);
});

it('toArray() preserves every optional field when populated', function (): void {
    $command = new Command(
        id: 'theme:dark',
        label: 'Switch to dark theme',
        url: '?theme=dark',
        description: 'Toggle the dark colour scheme',
        category: 'Settings',
        icon: 'moon',
    );

    expect($command->toArray())->toBe([
        'id' => 'theme:dark',
        'label' => 'Switch to dark theme',
        'url' => '?theme=dark',
        'description' => 'Toggle the dark colour scheme',
        'category' => 'Settings',
        'icon' => 'moon',
    ]);
});

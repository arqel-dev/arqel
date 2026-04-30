<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\Providers\ThemeCommandProvider;

beforeEach(function (): void {
    $this->provider = new ThemeCommandProvider;
});

it('always returns three theme commands regardless of user or query', function (): void {
    expect($this->provider->provide(null, ''))->toHaveCount(3)
        ->and($this->provider->provide(null, 'anything'))->toHaveCount(3);
});

it('categorises every theme command under Settings', function (): void {
    $commands = $this->provider->provide(null, '');

    foreach ($commands as $command) {
        expect($command)->toBeInstanceOf(Command::class)
            ->and($command->category)->toBe('Settings');
    }
});

it('emits the expected ids, urls and icons for the three theme variants', function (): void {
    $commands = $this->provider->provide(null, '');

    $byId = [];
    foreach ($commands as $command) {
        $byId[$command->id] = $command;
    }

    expect($byId)->toHaveKeys(['theme:light', 'theme:dark', 'theme:system']);

    expect($byId['theme:light']->label)->toBe('Switch to light theme')
        ->and($byId['theme:light']->url)->toBe('?theme=light')
        ->and($byId['theme:light']->icon)->toBe('sun');

    expect($byId['theme:dark']->label)->toBe('Switch to dark theme')
        ->and($byId['theme:dark']->url)->toBe('?theme=dark')
        ->and($byId['theme:dark']->icon)->toBe('moon');

    expect($byId['theme:system']->label)->toBe('Use system theme')
        ->and($byId['theme:system']->url)->toBe('?theme=system')
        ->and($byId['theme:system']->icon)->toBe('monitor');
});

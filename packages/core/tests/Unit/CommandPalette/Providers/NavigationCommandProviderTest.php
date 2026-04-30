<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\Providers\NavigationCommandProvider;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Resources\BrokenIconResource;
use Arqel\Core\Tests\Fixtures\Resources\BrokenSlugResource;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

beforeEach(function (): void {
    $this->registry = new ResourceRegistry;
    $this->provider = new NavigationCommandProvider($this->registry);
});

it('emits a navigation command for every registered resource', function (): void {
    $this->registry->register(UserResource::class);
    $this->registry->register(PostResource::class);

    $commands = $this->provider->provide(null, '');

    expect($commands)->toHaveCount(2)
        ->each->toBeInstanceOf(Command::class);

    $userCommand = $commands[0];
    expect($userCommand->id)->toBe('nav:users')
        ->and($userCommand->label)->toBe('Go to Users')
        ->and($userCommand->url)->toBe('/admin/users')
        ->and($userCommand->category)->toBe('Navigation')
        ->and($userCommand->icon)->toBe('heroicon-o-user');

    $postCommand = $commands[1];
    expect($postCommand->id)->toBe('nav:posts')
        ->and($postCommand->label)->toBe('Go to Posts')
        ->and($postCommand->url)->toBe('/admin/posts')
        ->and($postCommand->category)->toBe('Navigation')
        ->and($postCommand->icon)->toBeNull();
});

it('returns an empty array when the registry is empty', function (): void {
    expect($this->provider->provide(null, ''))->toBe([]);
});

it('still emits a command when getNavigationIcon throws (icon downgraded to null)', function (): void {
    $this->registry->register(BrokenIconResource::class);

    $commands = $this->provider->provide(null, '');

    expect($commands)->toHaveCount(1);
    $command = $commands[0];
    expect($command->id)->toBe('nav:broken-icon')
        ->and($command->label)->toBe('Go to Broken Icons')
        ->and($command->url)->toBe('/admin/broken-icon')
        ->and($command->icon)->toBeNull();
});

it('silently skips resources whose getSlug throws', function (): void {
    $this->registry->register(BrokenSlugResource::class);
    $this->registry->register(UserResource::class);

    $commands = $this->provider->provide(null, '');

    expect($commands)->toHaveCount(1)
        ->and($commands[0]->id)->toBe('nav:users');
});

it('ignores user and query arguments (filtering is the registry job)', function (): void {
    $this->registry->register(UserResource::class);

    $first = $this->provider->provide(null, '');
    $second = $this->provider->provide(null, 'unrelated query');

    expect($first)->toEqual($second);
});

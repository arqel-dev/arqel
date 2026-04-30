<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandProvider;
use Arqel\Core\CommandPalette\CommandRegistry;
use Illuminate\Contracts\Auth\Authenticatable;

beforeEach(function (): void {
    $this->registry = new CommandRegistry;
});

function makeCommand(string $id, string $label, ?string $description = null): Command
{
    return new Command(
        id: $id,
        label: $label,
        url: '/admin/'.$id,
        description: $description,
    );
}

it('registers a static command and exposes it via all()', function (): void {
    $command = makeCommand('users', 'Users');

    $this->registry->register($command);

    expect($this->registry->all())->toBe([$command]);
});

it('returns every registered command when the query is empty', function (): void {
    $a = makeCommand('users', 'Users');
    $b = makeCommand('posts', 'Posts');

    $this->registry->register($a);
    $this->registry->register($b);

    $resolved = $this->registry->resolveFor(null, '');

    expect($resolved)
        ->toHaveCount(2)
        ->toContain($a)
        ->toContain($b);
});

it('accepts a CommandProvider instance via registerProvider()', function (): void {
    $provider = new class implements CommandProvider
    {
        public function provide(?Authenticatable $user, string $query): array
        {
            return [makeCommand('lazy', 'Lazy provider')];
        }
    };

    $this->registry->registerProvider($provider);

    $resolved = $this->registry->resolveFor(null, '');

    expect($resolved)
        ->toHaveCount(1)
        ->and($resolved[0]->id)->toBe('lazy');
});

it('accepts a Closure provider and wraps it in a CommandProvider adapter', function (): void {
    $this->registry->registerProvider(
        fn (?Authenticatable $user, string $query): array => [
            makeCommand('closure', 'Closure provider'),
        ],
    );

    $resolved = $this->registry->resolveFor(null, '');

    expect($resolved)
        ->toHaveCount(1)
        ->and($resolved[0]->label)->toBe('Closure provider');
});

it('merges static commands with provider output in resolveFor()', function (): void {
    $static = makeCommand('users', 'Users');
    $this->registry->register($static);

    $this->registry->registerProvider(
        fn (?Authenticatable $user, string $query): array => [makeCommand('posts', 'Posts')],
    );

    $resolved = $this->registry->resolveFor(null, '');

    expect($resolved)->toHaveCount(2)
        ->and(array_map(fn (Command $c) => $c->id, $resolved))
        ->toContain('users', 'posts');
});

it('drops zero-scored commands when the query does not match', function (): void {
    $this->registry->register(makeCommand('users', 'Users'));
    $this->registry->register(makeCommand('posts', 'Posts'));

    $resolved = $this->registry->resolveFor(null, 'usr');

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0]->id)->toBe('users');
});

it('caps the resolved list at 20 commands', function (): void {
    for ($i = 0; $i < 30; $i++) {
        $this->registry->register(makeCommand("cmd-{$i}", "Command {$i}"));
    }

    $resolved = $this->registry->resolveFor(null, '');

    expect($resolved)->toHaveCount(20);
});

it('clears both static commands and providers', function (): void {
    $this->registry->register(makeCommand('users', 'Users'));
    $this->registry->registerProvider(
        fn (?Authenticatable $user, string $query): array => [makeCommand('posts', 'Posts')],
    );

    $this->registry->clear();

    expect($this->registry->all())->toBe([])
        ->and($this->registry->resolveFor(null, ''))->toBe([]);
});

it('all() does not include commands produced by providers', function (): void {
    $this->registry->registerProvider(
        fn (?Authenticatable $user, string $query): array => [makeCommand('lazy', 'Lazy')],
    );

    expect($this->registry->all())->toBe([]);
});

it('preserves static-before-provider order when scores tie (empty query)', function (): void {
    $static = makeCommand('static', 'Static command');
    $this->registry->register($static);

    $this->registry->registerProvider(
        fn (?Authenticatable $user, string $query): array => [makeCommand('lazy', 'Lazy command')],
    );

    $resolved = $this->registry->resolveFor(null, '');

    // Both score 100 on an empty query; tie-break by insertion order
    // means the static command must come first.
    expect($resolved)->toHaveCount(2)
        ->and($resolved[0]->id)->toBe('static')
        ->and($resolved[1]->id)->toBe('lazy');
});

it('forwards user and query to providers', function (): void {
    $captured = (object) ['user' => 'untouched', 'query' => 'untouched'];

    $this->registry->registerProvider(function (?Authenticatable $user, string $query) use ($captured): array {
        $captured->user = $user;
        $captured->query = $query;

        return [];
    });

    $this->registry->resolveFor(null, 'hello');

    expect($captured->user)->toBeNull()
        ->and($captured->query)->toBe('hello');
});

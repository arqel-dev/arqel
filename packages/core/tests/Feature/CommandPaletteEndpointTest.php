<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandRegistry;
use Illuminate\Foundation\Auth\User as AuthUser;

beforeEach(function (): void {
    /** @var CommandRegistry $registry */
    $registry = app(CommandRegistry::class);
    $registry->clear();
    $this->registry = $registry;
});

function registerSampleCommands(CommandRegistry $registry): void
{
    $registry->register(new Command(
        id: 'users.index',
        label: 'Users',
        url: '/admin/users',
        description: 'Manage user accounts',
        category: 'navigation',
    ));

    $registry->register(new Command(
        id: 'posts.index',
        label: 'Posts',
        url: '/admin/posts',
        description: 'Manage blog posts',
        category: 'navigation',
    ));

    $registry->register(new Command(
        id: 'theme.toggle',
        label: 'Toggle theme',
        url: '/admin/theme/toggle',
        category: 'settings',
    ));
}

it('returns all commands as JSON when the query is empty', function (): void {
    registerSampleCommands($this->registry);

    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 't@e.dev']);

    $response = $this->actingAs($user)->getJson('/admin/commands');

    $response->assertOk()
        ->assertJsonStructure([
            'commands' => [
                ['id', 'label', 'url', 'description', 'category', 'icon'],
            ],
        ])
        ->assertJsonCount(3, 'commands');
});

it('filters commands using the q query parameter', function (): void {
    registerSampleCommands($this->registry);

    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 't@e.dev']);

    $response = $this->actingAs($user)->getJson('/admin/commands?q=users');

    $response->assertOk();

    $payload = $response->json();
    expect($payload['commands'])->toHaveCount(1)
        ->and($payload['commands'][0]['id'])->toBe('users.index');
});

it('rejects guests by failing to authenticate', function (): void {
    registerSampleCommands($this->registry);

    // Register a stub `login` route so the `auth` middleware has
    // somewhere to redirect to instead of throwing a RouteNotFound.
    Illuminate\Support\Facades\Route::get('/login', fn () => 'login')->name('login');

    $response = $this->getJson('/admin/commands');

    // `getJson` sets Accept: application/json so the auth middleware
    // returns 401 instead of redirecting.
    expect($response->getStatusCode())->toBe(401);
});

it('returns the response shape the React palette expects', function (): void {
    $this->registry->register(new Command(
        id: 'theme',
        label: 'Toggle theme',
        url: '/admin/theme',
        description: null,
        category: 'settings',
        icon: 'sun',
    ));

    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 't@e.dev']);

    $response = $this->actingAs($user)->getJson('/admin/commands');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => 'theme',
            'label' => 'Toggle theme',
            'url' => '/admin/theme',
            'description' => null,
            'category' => 'settings',
            'icon' => 'sun',
        ]);
});

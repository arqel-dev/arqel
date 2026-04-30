<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\CommandRegistry;
use Arqel\Core\CommandPalette\Providers\NavigationCommandProvider;
use Arqel\Core\CommandPalette\Providers\ThemeCommandProvider;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;
use Illuminate\Foundation\Auth\User as AuthUser;

beforeEach(function (): void {
    /** @var CommandRegistry $registry */
    $registry = app(CommandRegistry::class);
    // Wipe static commands but keep the providers wired during boot
    // (NavigationCommandProvider + ThemeCommandProvider) — those are
    // the subject under test.
    $registry->clear();

    /** @var ResourceRegistry $resources */
    $resources = app(ResourceRegistry::class);
    $resources->clear();

    // Re-register the built-in providers manually after `clear()`
    // (which dropped them along with the static commands).
    $resources->register(UserResource::class);
    $registry->registerProvider(app(NavigationCommandProvider::class));
    $registry->registerProvider(app(ThemeCommandProvider::class));
});

function authenticate(): AuthUser
{
    $user = new AuthUser;
    $user->forceFill(['id' => 1, 'name' => 'Test', 'email' => 't@e.dev']);

    return $user;
}

it('returns the navigation command for a registered resource matching the query', function (): void {
    $response = $this->actingAs(authenticate())->getJson('/admin/commands?q=user');

    $response->assertOk();

    $payload = $response->json();

    $ids = array_column($payload['commands'], 'id');
    expect($ids)->toContain('nav:users');

    $userCommand = collect($payload['commands'])->firstWhere('id', 'nav:users');
    expect($userCommand)->not->toBeNull()
        ->and($userCommand['label'])->toBe('Go to Users')
        ->and($userCommand['url'])->toBe('/admin/users')
        ->and($userCommand['category'])->toBe('Navigation');
});

it('returns the theme:dark command when the query matches dark', function (): void {
    $response = $this->actingAs(authenticate())->getJson('/admin/commands?q=dark');

    $response->assertOk();

    $payload = $response->json();

    $ids = array_column($payload['commands'], 'id');
    expect($ids)->toContain('theme:dark');

    $darkCommand = collect($payload['commands'])->firstWhere('id', 'theme:dark');
    expect($darkCommand)->not->toBeNull()
        ->and($darkCommand['label'])->toBe('Switch to dark theme')
        ->and($darkCommand['url'])->toBe('?theme=dark')
        ->and($darkCommand['category'])->toBe('Settings')
        ->and($darkCommand['icon'])->toBe('moon');
});

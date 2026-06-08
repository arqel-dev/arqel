<?php

declare(strict_types=1);

use Arqel\Core\Panel\Panel;
use Arqel\Core\Tests\Fixtures\Resources\PostResource;
use Arqel\Core\Tests\Fixtures\Resources\UserResource;

it('exposes the panel id as a readonly property', function (): void {
    $panel = new Panel('admin');

    expect($panel->id)->toBe('admin');
});

it('uses sensible defaults until configured', function (): void {
    $panel = new Panel('admin');

    expect($panel->getPath())->toBe('/admin')
        ->and($panel->getBrand())->toMatchArray(['name' => 'Arqel', 'logo' => null])
        ->and($panel->getTheme())->toBe('default')
        ->and($panel->isDarkModeEnabled())->toBeFalse()
        ->and($panel->getMiddleware())->toBe(['web'])
        ->and($panel->getResources())->toBe([])
        ->and($panel->getAuthGuard())->toBe('web')
        ->and($panel->getTenantScope())->toBeNull();
});

it('supports a fluent configuration chain', function (): void {
    $panel = (new Panel('admin'))
        ->path('/backoffice')
        ->brand('Acme', '/img/logo.svg')
        ->theme('midnight')
        ->primaryColor('#ff0066')
        ->darkMode()
        ->middleware(['web', 'auth', 'verified'])
        ->resources([UserResource::class, PostResource::class])
        ->widgets([])
        ->navigationGroups(['Content', 'System'])
        ->authGuard('admin')
        ->tenant('team_id');

    expect($panel->getPath())->toBe('/backoffice')
        ->and($panel->getBrand())->toBe(['name' => 'Acme', 'logo' => '/img/logo.svg'])
        ->and($panel->getTheme())->toBe('midnight')
        ->and($panel->getPrimaryColor())->toBe('#ff0066')
        ->and($panel->isDarkModeEnabled())->toBeTrue()
        ->and($panel->getMiddleware())->toBe(['web', 'auth', 'verified'])
        ->and($panel->getResources())->toBe([UserResource::class, PostResource::class])
        ->and($panel->getNavigationGroups())->toBe(['Content', 'System'])
        ->and($panel->getAuthGuard())->toBe('admin')
        ->and($panel->getTenantScope())->toBe('team_id');
});

it('normalizes the panel path so it always starts with a slash', function (): void {
    expect((new Panel('admin'))->path('backoffice')->getPath())->toBe('/backoffice')
        ->and((new Panel('admin'))->path('/backoffice')->getPath())->toBe('/backoffice');
});

it('can disable dark mode explicitly', function (): void {
    $panel = (new Panel('admin'))->darkMode()->darkMode(false);

    expect($panel->isDarkModeEnabled())->toBeFalse();
});

it('writes the reset expiration to the users broker for a default-guard panel (#191)', function (): void {
    config()->set('auth.passwords.users.expire', 60);
    config()->set('auth.passwords.admins.expire', 60);

    (new Panel('admin'))->passwordResetExpirationMinutes(15);

    expect(config('auth.passwords.users.expire'))->toBe(15)
        ->and(config('auth.passwords.admins.expire'))->toBe(60);
});

it('writes the reset expiration to the panel-guard broker, not users (#191)', function (): void {
    config()->set('auth.passwords.users.expire', 60);
    config()->set('auth.passwords.admins.expire', 60);
    config()->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
    config()->set('auth.passwords.admins.provider', 'admins');

    (new Panel('admin'))->authGuard('admin')->passwordResetExpirationMinutes(15);

    expect(config('auth.passwords.admins.expire'))->toBe(15)
        ->and(config('auth.passwords.users.expire'))->toBe(60);
});

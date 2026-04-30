<?php

declare(strict_types=1);

use Arqel\Tenant\TenantManager;
use Arqel\Tenant\Tests\Fixtures\Tenant;
use Arqel\Tenant\Theming\TenantTheme;
use Arqel\Tenant\Theming\TenantThemeResolver;

it('returns an empty theme when the manager has no current tenant', function (): void {
    $manager = new TenantManager;
    $resolver = new TenantThemeResolver($manager);

    $theme = $resolver->resolve();

    expect($theme)->toBeInstanceOf(TenantTheme::class);
    expect($theme->isEmpty())->toBeTrue();
});

it('returns a populated theme when the manager has a current tenant', function (): void {
    $tenant = new Tenant([
        'primary_color' => '#abc123',
        'logo_url' => '/logo.svg',
    ]);
    $manager = new TenantManager;
    $manager->set($tenant);

    $resolver = new TenantThemeResolver($manager);
    $theme = $resolver->resolve();

    expect($theme->primaryColor)->toBe('#abc123');
    expect($theme->logoUrl)->toBe('/logo.svg');
    expect($theme->isEmpty())->toBeFalse();
});

it('is bound as a singleton in the container after the package boots', function (): void {
    $first = app(TenantThemeResolver::class);
    $second = app(TenantThemeResolver::class);

    expect($first)->toBeInstanceOf(TenantThemeResolver::class);
    expect($second)->toBe($first);
});

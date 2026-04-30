<?php

declare(strict_types=1);

use Arqel\Tenant\Tests\Fixtures\Tenant;
use Arqel\Tenant\Theming\TenantTheme;

it('returns an empty theme when no tenant is provided', function (): void {
    $theme = TenantTheme::fromTenant(null);

    expect($theme->primaryColor)->toBeNull();
    expect($theme->logoUrl)->toBeNull();
    expect($theme->fontFamily)->toBeNull();
    expect($theme->secondaryColor)->toBeNull();
    expect($theme->faviconUrl)->toBeNull();
    expect($theme->isEmpty())->toBeTrue();
});

it('reads all 5 attributes when present on the tenant model', function (): void {
    $tenant = new Tenant([
        'primary_color' => '#ff0000',
        'logo_url' => '/logos/acme.png',
        'font_family' => 'Inter',
        'secondary_color' => '#00ff00',
        'favicon_url' => '/favicons/acme.ico',
    ]);

    $theme = TenantTheme::fromTenant($tenant);

    expect($theme->primaryColor)->toBe('#ff0000');
    expect($theme->logoUrl)->toBe('/logos/acme.png');
    expect($theme->fontFamily)->toBe('Inter');
    expect($theme->secondaryColor)->toBe('#00ff00');
    expect($theme->faviconUrl)->toBe('/favicons/acme.ico');
    expect($theme->isEmpty())->toBeFalse();
});

it('treats missing attributes as null', function (): void {
    $tenant = new Tenant(['primary_color' => '#ff0000']);

    $theme = TenantTheme::fromTenant($tenant);

    expect($theme->primaryColor)->toBe('#ff0000');
    expect($theme->logoUrl)->toBeNull();
    expect($theme->fontFamily)->toBeNull();
});

it('treats non-string attributes as null defensively', function (): void {
    $tenant = new Tenant([
        'primary_color' => ['array', 'value'],
        'logo_url' => 42,
        'font_family' => 'Inter',
    ]);

    $theme = TenantTheme::fromTenant($tenant);

    expect($theme->primaryColor)->toBeNull();
    expect($theme->logoUrl)->toBeNull();
    expect($theme->fontFamily)->toBe('Inter');
});

it('treats empty-string attributes as null', function (): void {
    $tenant = new Tenant([
        'primary_color' => '',
        'logo_url' => '/x',
    ]);

    $theme = TenantTheme::fromTenant($tenant);

    expect($theme->primaryColor)->toBeNull();
    expect($theme->logoUrl)->toBe('/x');
});

it('serialises to a 5-key array via toArray()', function (): void {
    $theme = new TenantTheme(
        primaryColor: '#ff0000',
        fontFamily: 'Inter',
    );

    expect($theme->toArray())->toBe([
        'primaryColor' => '#ff0000',
        'logoUrl' => null,
        'fontFamily' => 'Inter',
        'secondaryColor' => null,
        'faviconUrl' => null,
    ]);
});

it('isEmpty() returns false when any single prop is set', function (): void {
    $theme = new TenantTheme(faviconUrl: '/x.ico');

    expect($theme->isEmpty())->toBeFalse();
});

it('is constructible directly with all-null defaults', function (): void {
    $theme = new TenantTheme;

    expect($theme->isEmpty())->toBeTrue();
});

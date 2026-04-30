<?php

declare(strict_types=1);

use Arqel\Tenant\Theming\CssVarsRenderer;
use Arqel\Tenant\Theming\TenantTheme;

it('returns an empty string for an empty theme', function (): void {
    $rendered = CssVarsRenderer::renderInlineStyle(new TenantTheme);

    expect($rendered)->toBe('');
});

it('emits all 5 CSS variables for a fully populated theme', function (): void {
    $theme = new TenantTheme(
        primaryColor: '#ff0000',
        logoUrl: '/logo.png',
        fontFamily: 'Inter',
        secondaryColor: '#00ff00',
        faviconUrl: '/favicon.ico',
    );

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toStartWith('<style>:root { ');
    expect($rendered)->toEndWith(' }</style>');
    expect($rendered)->toContain('--color-primary: #ff0000;');
    expect($rendered)->toContain('--color-secondary: #00ff00;');
    expect($rendered)->toContain('--font-family: Inter;');
    expect($rendered)->toContain('--logo-url: /logo.png;');
    expect($rendered)->toContain('--favicon-url: /favicon.ico;');
});

it('omits unset props from the output', function (): void {
    $theme = new TenantTheme(primaryColor: '#ff0000');

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toContain('--color-primary: #ff0000;');
    expect($rendered)->not->toContain('--color-secondary');
    expect($rendered)->not->toContain('--font-family');
});

it('drops props containing < (HTML injection defense)', function (): void {
    $theme = new TenantTheme(
        primaryColor: '"; }</style><script>alert(1)</script>',
        fontFamily: 'Inter',
    );

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->not->toContain('script');
    expect($rendered)->not->toContain('--color-primary');
    expect($rendered)->toContain('--font-family: Inter;');
});

it('drops props containing >', function (): void {
    $theme = new TenantTheme(logoUrl: '/path>injection');

    $rendered = CssVarsRenderer::renderInlineStyle(theme: $theme);

    expect($rendered)->toBe('');
});

it('drops props containing double-quote', function (): void {
    $theme = new TenantTheme(faviconUrl: '/x" onclick="evil()');

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toBe('');
});

it('htmlspecialchars-escapes safe values containing &', function (): void {
    $theme = new TenantTheme(primaryColor: '#ff & 00');

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toContain('--color-primary: #ff &amp; 00;');
});

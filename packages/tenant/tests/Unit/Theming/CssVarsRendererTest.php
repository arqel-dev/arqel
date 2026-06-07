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
    expect($rendered)->toContain("--logo-url: url('/logo.png');");
    expect($rendered)->toContain("--favicon-url: url('/favicon.ico');");
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

it('drops a color value containing & (not a valid color)', function (): void {
    $theme = new TenantTheme(primaryColor: '#ff & 00');

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toBe('');
});

it('neutralizes a }-breakout CSS injection via primaryColor', function (): void {
    $theme = new TenantTheme(
        primaryColor: 'red } body{background:url(//evil/?c=secret)} :root{--x:1',
    );

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    // The malicious value must NOT inject a new rule.
    expect($rendered)->not->toContain('body{');
    expect($rendered)->not->toContain('}');
    // Strongest assertion: the var is simply omitted.
    expect($rendered)->toBe('');
});

it('neutralizes a }-breakout CSS injection via fontFamily', function (): void {
    $theme = new TenantTheme(
        fontFamily: 'Inter } body{display:none} :root{x:1',
    );

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->not->toContain('body{');
    expect($rendered)->not->toContain('}');
    expect($rendered)->toBe('');
});

it('neutralizes a }-breakout CSS injection via logoUrl', function (): void {
    $theme = new TenantTheme(
        logoUrl: 'https://cdn/a.png) } body{background:url(//evil)} :root{--x:url(',
    );

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->not->toContain('body{');
    expect($rendered)->not->toContain('}');
    expect($rendered)->toBe('');
});

it('rejects a javascript: scheme in a url slot', function (): void {
    $theme = new TenantTheme(faviconUrl: 'javascript:alert(1)');

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toBe('');
});

it('accepts an rgb() functional color literal', function (): void {
    $theme = new TenantTheme(primaryColor: 'rgb(51, 102, 255)');

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toContain('--color-primary: rgb(51, 102, 255);');
});

it('accepts a CSS named color', function (): void {
    $theme = new TenantTheme(secondaryColor: 'rebeccapurple');

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toContain('--color-secondary: rebeccapurple;');
});

it('renders a full set of valid values without regression', function (): void {
    $theme = new TenantTheme(
        primaryColor: '#3366ff',
        logoUrl: 'https://cdn.example.com/logo.png',
        fontFamily: 'Inter, sans-serif',
        secondaryColor: '#0f0',
        faviconUrl: '/favicon.ico',
    );

    $rendered = CssVarsRenderer::renderInlineStyle($theme);

    expect($rendered)->toStartWith('<style>:root { ');
    expect($rendered)->toEndWith(' }</style>');
    expect($rendered)->toContain('--color-primary: #3366ff;');
    expect($rendered)->toContain('--color-secondary: #0f0;');
    expect($rendered)->toContain('--font-family: Inter, sans-serif;');
    expect($rendered)->toContain("--logo-url: url('https://cdn.example.com/logo.png');");
    expect($rendered)->toContain("--favicon-url: url('/favicon.ico');");
});

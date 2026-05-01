<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;

it('renders og:title, og:description and JSON-LD on plugin detail page', function (): void {
    Plugin::create([
        'slug' => 'awesome',
        'name' => 'Awesome Plugin',
        'description' => 'A fine plugin to make admin great again.',
        'type' => 'field',
        'github_url' => 'https://github.com/x/awesome',
        'license' => 'MIT',
        'status' => 'published',
        'price_cents' => 1990,
        'currency' => 'USD',
    ]);

    $response = $this->get('/plugins/awesome');

    $response->assertOk();
    $response->assertSee('og:title', false);
    $response->assertSee('Awesome Plugin — Arqel Marketplace', false);
    $response->assertSee('A fine plugin to make admin great again.', false);
    $response->assertSee('application/ld+json', false);
    $response->assertSee('"@type":"Product"', false);
    $response->assertSee('"priceCurrency":"USD"', false);
});

it('renders og:image with landing-specific image on landing page', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('og:image', false);
    $response->assertSee('/images/og/marketplace-landing.png', false);
    $response->assertSee('Plugins for Laravel admin panels', false);
});

it('reflects category filter in browse meta description', function (): void {
    $response = $this->get('/browse?type=widget');

    $response->assertOk();
    $response->assertSee('Browse plugins do tipo widget', false);
    $response->assertSee('Browse plugins — Arqel Marketplace', false);
});

it('escapes meta tag content to prevent XSS', function (): void {
    Plugin::create([
        'slug' => 'xss-test',
        'name' => 'Evil <script>alert(1)</script>',
        'description' => 'Has "quotes" and <tags> in it',
        'type' => 'field',
        'github_url' => 'https://github.com/x/xss',
        'license' => 'MIT',
        'status' => 'published',
    ]);

    $response = $this->get('/plugins/xss-test');

    $response->assertOk();
    $body = (string) $response->getContent();

    // og:title and og:description meta values must be HTML-escaped.
    expect($body)->toContain('content="Evil &lt;script&gt;alert(1)&lt;/script&gt; — Arqel Marketplace"');
    expect($body)->toContain('Has &quot;quotes&quot; and &lt;tags&gt; in it');
});

it('renders default site_name and twitter:card on every page', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('og:site_name', false);
    $response->assertSee('Arqel Marketplace', false);
    $response->assertSee('twitter:card', false);
    $response->assertSee('summary_large_image', false);
});

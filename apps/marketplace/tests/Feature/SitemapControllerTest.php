<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\Publisher;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::forget('marketplace:sitemap');
});

it('returns valid XML with content-type application/xml', function (): void {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/xml');

    $body = $response->getContent();
    expect($body)->toBeString();
    /** @var string $body */
    $xml = simplexml_load_string($body);
    expect($xml)->not->toBeFalse();
});

it('contains URLs for published plugins', function (): void {
    Plugin::create([
        'slug' => 'awesome-field',
        'name' => 'Awesome Field',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/awesome-field',
        'status' => 'published',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertSee('/plugins/awesome-field', false);
});

it('excludes plugins not in published status', function (): void {
    Plugin::create([
        'slug' => 'draft-plugin',
        'name' => 'Draft',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/draft',
        'status' => 'draft',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertDontSee('/plugins/draft-plugin', false);
});

it('contains publisher URLs and static landing/browse routes', function (): void {
    Publisher::create([
        'slug' => 'acme',
        'name' => 'Acme Co',
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertSee('/publishers/acme', false);
    $response->assertSee('<loc>', false);
    // landing + browse always present
    $body = (string) $response->getContent();
    expect($body)->toContain('/browse');
});

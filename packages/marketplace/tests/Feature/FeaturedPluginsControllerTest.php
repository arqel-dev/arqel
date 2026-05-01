<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;

function featuredPlugin(array $overrides = []): Plugin
{
    /** @var Plugin $plugin */
    $plugin = Plugin::query()->create(array_merge([
        'slug' => 'feat-'.uniqid(),
        'name' => 'Feat',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'license' => 'MIT',
        'status' => 'published',
    ], $overrides));

    return $plugin;
}

it('returns only featured published plugins', function (): void {
    featuredPlugin(['slug' => 'f-yes', 'featured' => true, 'featured_at' => now()]);
    featuredPlugin(['slug' => 'f-no', 'featured' => false]);
    featuredPlugin(['slug' => 'f-yes-2', 'featured' => true, 'featured_at' => now()->subDay()]);

    $response = $this->getJson('/api/marketplace/featured');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs)->toContain('f-yes')
        ->and($slugs)->toContain('f-yes-2')
        ->and($slugs)->not->toContain('f-no');
});

it('excludes non-published plugins even if featured', function (): void {
    featuredPlugin(['slug' => 'draft-feat', 'status' => 'draft', 'featured' => true]);
    featuredPlugin(['slug' => 'pub-feat', 'status' => 'published', 'featured' => true]);

    $response = $this->getJson('/api/marketplace/featured');

    $response->assertOk();
    $slugs = array_column($response->json('data'), 'slug');
    expect($slugs)->toContain('pub-feat')
        ->and($slugs)->not->toContain('draft-feat');
});

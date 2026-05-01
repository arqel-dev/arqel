<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginInstallation;
use Arqel\Marketplace\Models\PluginReview;
use Arqel\Marketplace\Models\Publisher;
use Inertia\Testing\AssertableInertia;

it('renders the Marketplace/PublisherProfile component for a known slug', function (): void {
    $publisher = Publisher::create([
        'slug' => 'acme',
        'name' => 'Acme Corp',
        'bio' => 'We build great plugins.',
        'github_url' => 'https://github.com/acme',
        'verified' => true,
    ]);

    Plugin::create([
        'slug' => 'acme-widget',
        'name' => 'Acme Widget',
        'description' => 'desc',
        'type' => 'widget',
        'github_url' => 'https://github.com/acme/widget',
        'status' => 'published',
        'publisher_id' => $publisher->id,
    ]);

    $this->get('/publishers/acme')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/PublisherProfile')
            ->where('publisher.slug', 'acme')
            ->where('publisher.verified', true)
            ->has('plugins', 1)
            ->has('stats'),
        );
});

it('returns 404 for an unknown publisher slug', function (): void {
    $this->get('/publishers/nope')->assertNotFound();
});

it('lists only published plugins on the profile', function (): void {
    $publisher = Publisher::create([
        'slug' => 'acme',
        'name' => 'Acme',
    ]);

    Plugin::create([
        'slug' => 'pub',
        'name' => 'Pub',
        'description' => 'd',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/pub',
        'status' => 'published',
        'publisher_id' => $publisher->id,
    ]);

    Plugin::create([
        'slug' => 'pending',
        'name' => 'Pending',
        'description' => 'd',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/pending',
        'status' => 'pending',
        'publisher_id' => $publisher->id,
    ]);

    $this->get('/publishers/acme')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/PublisherProfile')
            ->has('plugins', 1)
            ->where('plugins.0.slug', 'pub'),
        );
});

it('does not include plugins from other publishers', function (): void {
    $a = Publisher::create(['slug' => 'a', 'name' => 'A']);
    $b = Publisher::create(['slug' => 'b', 'name' => 'B']);

    Plugin::create([
        'slug' => 'mine',
        'name' => 'Mine',
        'description' => 'd',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/m',
        'status' => 'published',
        'publisher_id' => $a->id,
    ]);

    Plugin::create([
        'slug' => 'theirs',
        'name' => 'Theirs',
        'description' => 'd',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/t',
        'status' => 'published',
        'publisher_id' => $b->id,
    ]);

    $this->get('/publishers/a')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('plugins', 1)
            ->where('plugins.0.slug', 'mine'),
        );
});

it('returns aggregated stats for the publisher', function (): void {
    $publisher = Publisher::create([
        'slug' => 'acme',
        'name' => 'Acme',
    ]);

    $plugin = Plugin::create([
        'slug' => 'p',
        'name' => 'P',
        'description' => 'd',
        'type' => 'widget',
        'github_url' => 'https://github.com/x/p',
        'status' => 'published',
        'publisher_id' => $publisher->id,
    ]);

    PluginInstallation::create([
        'plugin_id' => $plugin->id,
        'installed_at' => now(),
    ]);
    PluginInstallation::create([
        'plugin_id' => $plugin->id,
        'installed_at' => now(),
    ]);
    PluginReview::create([
        'plugin_id' => $plugin->id,
        'user_id' => 1,
        'stars' => 4,
        'status' => 'published',
    ]);

    $this->get('/publishers/acme')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.plugins_count', 1)
            ->where('stats.total_downloads', 2)
            ->where('stats.avg_rating', 4),
        );
});

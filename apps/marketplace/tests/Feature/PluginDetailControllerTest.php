<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Inertia\Testing\AssertableInertia;

it('renders the Marketplace/PluginDetail component for a published plugin', function (): void {
    Plugin::create([
        'slug' => 'awesome',
        'name' => 'Awesome',
        'description' => 'a fine plugin',
        'type' => 'field',
        'github_url' => 'https://github.com/x/awesome',
        'status' => 'published',
    ]);

    $this->get('/plugins/awesome')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/PluginDetail')
            ->where('plugin.slug', 'awesome')
            ->has('versions')
            ->has('reviews.data')
            ->has('related'),
        );
});

it('returns 404 for an unknown slug', function (): void {
    $this->get('/plugins/does-not-exist')->assertNotFound();
});

it('hides plugins that are not published', function (): void {
    Plugin::create([
        'slug' => 'pending-one',
        'name' => 'Pending',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/p',
        'status' => 'pending',
    ]);

    $this->get('/plugins/pending-one')->assertNotFound();
});

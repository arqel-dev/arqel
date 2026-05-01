<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginCategory;
use Inertia\Testing\AssertableInertia;

it('renders the Marketplace/Landing component', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Marketplace/Landing'));
});

it('exposes featured, trending, newPlugins and categories props', function (): void {
    Plugin::create([
        'slug' => 'feat-1',
        'name' => 'Featured 1',
        'description' => 'desc',
        'type' => 'field',
        'github_url' => 'https://github.com/x/y',
        'status' => 'published',
        'featured' => true,
        'trending_score' => 99.0,
    ]);

    PluginCategory::create(['slug' => 'cat-extra', 'name' => 'Extra', 'sort_order' => 99]);

    $this->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/Landing')
            ->has('featured', 1)
            ->has('trending', 1)
            ->has('newPlugins', 1)
            ->has('categories')
            ->where('categories.0.slug', fn ($slug) => is_string($slug)),
        );
});

it('renders empty state when there are no plugins', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/Landing')
            ->has('featured', 0)
            ->has('trending', 0)
            ->has('newPlugins', 0)
            ->has('categories'),
        );
});

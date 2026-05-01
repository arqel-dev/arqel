<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginCategory;
use Inertia\Testing\AssertableInertia;

function makePublished(string $slug, string $type = 'field'): Plugin
{
    return Plugin::create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'description' => 'desc',
        'type' => $type,
        'github_url' => 'https://github.com/x/'.$slug,
        'status' => 'published',
    ]);
}

it('renders the Marketplace/Browse component with paginated plugins', function (): void {
    foreach (range(1, 3) as $i) {
        makePublished('p-'.$i);
    }

    $this->get('/browse')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/Browse')
            ->has('plugins.data', 3)
            ->has('plugins.current_page')
            ->has('plugins.last_page'),
        );
});

it('paginates results when more than 20 plugins exist', function (): void {
    foreach (range(1, 25) as $i) {
        makePublished('plug-'.$i);
    }

    $this->get('/browse')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/Browse')
            ->has('plugins.data', 20)
            ->where('plugins.last_page', 2),
        );
});

it('filters plugins by category slug', function (): void {
    $matched = makePublished('matched');
    makePublished('other');

    $cat = PluginCategory::create(['slug' => 'custom-cat', 'name' => 'Custom Cat', 'sort_order' => 0]);
    $matched->categories()->attach($cat->id);

    $this->get('/browse?category=custom-cat')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('plugins.data', 1)
            ->where('plugins.data.0.slug', 'matched')
            ->where('filters.category', 'custom-cat'),
        );
});

it('filters plugins by type', function (): void {
    makePublished('a-field', 'field');
    makePublished('a-widget', 'widget');

    $this->get('/browse?type=widget')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('plugins.data', 1)
            ->where('plugins.data.0.slug', 'a-widget')
            ->where('filters.type', 'widget'),
        );
});

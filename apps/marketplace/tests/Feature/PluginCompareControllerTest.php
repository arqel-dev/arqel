<?php

declare(strict_types=1);

use Arqel\Marketplace\Models\Plugin;
use Inertia\Testing\AssertableInertia;

function makeComparePlugin(string $slug, string $status = 'published'): Plugin
{
    return Plugin::create([
        'slug' => $slug,
        'name' => ucfirst($slug),
        'description' => 'desc '.$slug,
        'type' => 'field',
        'github_url' => 'https://github.com/x/'.$slug,
        'license' => 'MIT',
        'latest_version' => '1.0.0',
        'status' => $status,
    ]);
}

it('renders compare page with two plugins', function (): void {
    makeComparePlugin('alpha');
    makeComparePlugin('beta');

    $this->get('/compare?slugs=alpha,beta')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/Compare')
            ->has('plugins', 2)
            ->where('plugins.0.slug', 'alpha')
            ->where('plugins.1.slug', 'beta')
            ->has('notFound', 0),
        );
});

it('renders compare page with three plugins', function (): void {
    makeComparePlugin('a');
    makeComparePlugin('b');
    makeComparePlugin('c');

    $this->get('/compare?slugs=a,b,c')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Marketplace/Compare')
            ->has('plugins', 3),
        );
});

it('returns 422 when fewer than 2 slugs provided', function (): void {
    makeComparePlugin('alpha');

    $this->get('/compare?slugs=alpha')->assertStatus(422);
});

it('returns 422 when more than 3 slugs provided', function (): void {
    foreach (['a', 'b', 'c', 'd'] as $slug) {
        makeComparePlugin($slug);
    }

    $this->get('/compare?slugs=a,b,c,d')->assertStatus(422);
});

it('reports unknown slugs in notFound array', function (): void {
    makeComparePlugin('alpha');

    $this->get('/compare?slugs=alpha,ghost')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('plugins', 1)
            ->where('plugins.0.slug', 'alpha')
            ->has('notFound', 1)
            ->where('notFound.0', 'ghost'),
        );
});

it('only returns published plugins', function (): void {
    makeComparePlugin('pub');
    makeComparePlugin('draft', 'pending');

    $this->get('/compare?slugs=pub,draft')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('plugins', 1)
            ->where('plugins.0.slug', 'pub')
            ->where('notFound.0', 'draft'),
        );
});

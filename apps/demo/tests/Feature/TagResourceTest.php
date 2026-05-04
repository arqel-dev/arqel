<?php

declare(strict_types=1);

use App\Models\Tag;
use Inertia\Testing\AssertableInertia;

it('renders the tags list page', function (): void {
    Tag::create(['name' => 'react', 'slug' => 'react', 'category' => 'frontend']);

    $this->get('/admin/tags')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Tags/Index')
            ->has('tags', 1)
            ->has('fields', 3),
        );
});

it('classifies tags via AI endpoint', function (): void {
    $response = $this->postJson('/admin/tags/ai/classify', ['name' => 'docker-deploy']);
    $response->assertOk();
    expect($response->json('category'))->toBe('devops');

    $other = $this->postJson('/admin/tags/ai/classify', ['name' => 'react-router']);
    expect($other->json('category'))->toBe('frontend');
});

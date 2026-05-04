<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use App\States\PostStates;
use Inertia\Testing\AssertableInertia;

it('renders admin dashboard with panel + stats', function (): void {
    $author = User::create([
        'name' => 'A',
        'email' => 'a@demo.test',
        'password' => 'x',
    ]);
    Post::create([
        'title' => 'Pub',
        'slug' => 'pub',
        'state' => PostStates::PUBLISHED,
        'author_id' => $author->id,
    ]);
    Post::create([
        'title' => 'Drft',
        'slug' => 'drft',
        'state' => PostStates::DRAFT,
        'author_id' => $author->id,
    ]);

    $this->get('/admin')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Dashboard')
            ->where('panel.id', 'admin')
            ->where('stats.posts', 2)
            ->where('stats.published', 1)
            ->where('stats.draft', 1),
        );
});

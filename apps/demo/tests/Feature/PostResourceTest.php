<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;
use App\States\PostStates;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->author = User::create([
        'name' => 'Demo Author',
        'email' => 'author@demo.test',
        'password' => 'x',
    ]);
});

it('renders the posts list page', function (): void {
    Post::create([
        'title' => 'Hello',
        'slug' => 'hello',
        'state' => PostStates::DRAFT,
        'author_id' => $this->author->id,
    ]);

    $this->get('/admin/posts')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Posts/Index')
            ->has('posts', 1)
            ->has('fields'),
        );
});

it('renders the post create form with all fields', function (): void {
    $this->get('/admin/posts/create')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Posts/Create')
            ->has('fields', 7),
        );
});

it('returns AI summary stub for the summary endpoint', function (): void {
    $response = $this->postJson('/admin/posts/ai/summary', [
        'title' => 'Test',
        'body' => 'Some body content',
    ]);

    $response->assertOk();
    expect($response->json('summary'))->toContain('Test');
    expect($response->json('provider'))->toBe('arqel-ai-stub');
});

it('transitions post state through the workflow', function (): void {
    $post = Post::create([
        'title' => 'Workflow',
        'slug' => 'workflow',
        'state' => PostStates::DRAFT,
        'author_id' => $this->author->id,
    ]);

    $ok = $this->postJson("/admin/posts/{$post->id}/transition", ['to' => PostStates::REVIEW]);
    $ok->assertOk();
    expect($ok->json('state'))->toBe(PostStates::REVIEW);

    $invalid = $this->postJson("/admin/posts/{$post->id}/transition", ['to' => PostStates::ARCHIVED]);
    $invalid->assertStatus(422);
});

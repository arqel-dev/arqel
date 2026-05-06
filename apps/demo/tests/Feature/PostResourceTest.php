<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin-test@arqel.test',
        ]);
    }

    public function test_lists_posts_on_the_resource_index(): void
    {
        Post::factory()->count(3)->create(['user_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get('/admin/posts')
            ->assertOk();
    }

    public function test_renders_the_create_form_for_posts(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/posts/create')
            ->assertOk();
    }

    public function test_persists_a_new_post_via_post_admin_posts(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/posts', [
                'title' => 'Test post',
                'slug' => 'test-post',
                'status' => 'draft',
                'featured' => false,
                'user_id' => $this->admin->id,
            ])
            ->assertRedirect();

        $this->assertTrue(Post::where('slug', 'test-post')->exists());
    }

    public function test_rejects_creation_without_required_title(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/posts', ['slug' => 'no-title', 'status' => 'draft'])
            ->assertSessionHasErrors('title');
    }
}

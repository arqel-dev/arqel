<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Arqel\Resources\PostResource;
use App\Models\Author;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Arqel\Export\Actions\ExportAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Per-cluster correctness gate for the `apps/showcase` dogfooding app.
 *
 * Authenticated feature tests that prove the showcase baseline is green
 * before the autonomous bug-detection loop probes it: resource
 * index/create render, Post store validation+create, Ticket workflow
 * states, dashboard render, tenant prop share+scope and the export
 * bulk action registration.
 */
final class ShowcaseSmokeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build the canonical admin: two tenants (Acme + Globex), an admin
     * attached to both with `current = Acme`. Mirrors the seeder
     * (admin@arqel.test / password) without depending on it running.
     */
    protected function makeAdmin(): User
    {
        $acme = Tenant::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme']);
        $globex = Tenant::firstOrCreate(['slug' => 'globex'], ['name' => 'Globex']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@arqel.test'],
            ['name' => 'Admin', 'password' => Hash::make('password')],
        );

        $admin->tenants()->syncWithoutDetaching([
            $acme->id => ['primary' => true],
            $globex->id => ['primary' => false],
        ]);

        $admin->update(['current_tenant_id' => $acme->id]);

        return $admin->fresh() ?? $admin;
    }

    public function test_resource_index_pages_render(): void
    {
        $admin = $this->makeAdmin();

        foreach (['posts', 'authors', 'tickets', 'settings'] as $slug) {
            $this->actingAs($admin)
                ->get("/admin/{$slug}")
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('arqel::index', false));
        }
    }

    public function test_resource_create_pages_render(): void
    {
        $admin = $this->makeAdmin();

        // Settings exercises the advanced fields server-side build.
        foreach (['posts', 'settings'] as $slug) {
            $this->actingAs($admin)
                ->get("/admin/{$slug}/create")
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('arqel::create', false));
        }
    }

    public function test_post_store_validates_then_creates(): void
    {
        $admin = $this->makeAdmin();

        // Invalid payload: `title` is required, `author_id` is required.
        $this->actingAs($admin)
            ->post('/admin/posts', [])
            ->assertSessionHasErrors(['title']);

        $author = Author::factory()->create();

        // Valid payload creates the Post and redirects to its edit page.
        $this->actingAs($admin)
            ->post('/admin/posts', [
                'title' => 'Hello Showcase',
                'slug' => 'hello-showcase',
                'body' => 'Body content.',
                'author_id' => $author->id,
                'status' => 'draft',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $post = Post::withoutGlobalScopes()->where('slug', 'hello-showcase')->first();

        $this->assertNotNull($post);
        $this->assertSame('Hello Showcase', $post->title);
        $this->assertSame('draft', $post->status);
        $this->assertSame($author->id, $post->author_id);
        // BelongsToTenant auto-fills tenant_id from the current tenant (Acme).
        $this->assertNotNull($post->tenant_id);
    }

    public function test_ticket_workflow_exposes_three_states(): void
    {
        $definition = (new Ticket)->arqelWorkflow();

        $this->assertSame('status', $definition->getField());
        $this->assertSame(
            ['open', 'in_progress', 'resolved'],
            array_keys($definition->getStates()),
        );

        // Exercise a simple persisted state transition (no transition
        // classes are declared, so this is a plain status set).
        $ticket = Ticket::factory()->create(['status' => 'open']);
        $ticket->update(['status' => 'in_progress']);

        $this->assertSame('in_progress', $ticket->fresh()?->status);
    }

    public function test_dashboard_renders_with_widgets(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('arqel::dashboard', false)
                ->has('dashboard.widgets'));
    }

    public function test_tenant_prop_is_shared_and_scoped(): void
    {
        $admin = $this->makeAdmin();
        $acme = Tenant::where('slug', 'acme')->firstOrFail();
        $globex = Tenant::where('slug', 'globex')->firstOrFail();
        $author = Author::factory()->create();

        Post::factory()->state([
            'tenant_id' => $acme->id,
            'author_id' => $author->id,
        ])->create();
        Post::factory()->state([
            'tenant_id' => $globex->id,
            'author_id' => $author->id,
        ])->create();

        $this->actingAs($admin)
            ->get('/admin/posts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('tenant.current')
                ->where('tenant.current.name', 'Acme')
                ->has('tenant.available', 2));
    }

    public function test_post_resource_exposes_export_bulk_action(): void
    {
        $bulkActions = (new PostResource)->table()->getBulkActions();

        $export = null;
        foreach ($bulkActions as $action) {
            if ($action instanceof ExportAction && $action->getName() === 'export') {
                $export = $action;

                break;
            }
        }

        $this->assertInstanceOf(
            ExportAction::class,
            $export,
            'PostResource table must expose an "export" bulk action.',
        );
    }
}

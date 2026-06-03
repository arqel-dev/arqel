<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_projects_page_shares_tenant_context_and_is_scoped(): void
    {
        $acme = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $globex = Tenant::create(['name' => 'Globex', 'slug' => 'globex']);

        Project::factory()->count(5)->state(['tenant_id' => $acme->id])->create();
        Project::factory()->count(5)->state(['tenant_id' => $globex->id])->create();

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@arqel.test',
            'password' => bcrypt('password'),
            'current_tenant_id' => $acme->id,
        ]);
        $admin->tenants()->sync([$acme->id, $globex->id]);

        $response = $this->actingAs($admin)->get('/admin/projects');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->has('tenantContext.current')
                ->where('tenantContext.current.name', 'Acme')
                ->has('tenantContext.available', 2),
        );
    }
}

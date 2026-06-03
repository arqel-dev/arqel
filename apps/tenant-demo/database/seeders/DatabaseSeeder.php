<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed two tenants (Acme, Globex) with 5 projects each and an admin
     * user attached to both, with Acme as the current tenant.
     *
     * Idempotent: re-running attaches tenants and resets current_tenant_id
     * without duplicating. This matters for the E2E setup, which runs
     * `arqel:make-user` after `migrate:fresh` and then re-seeds to attach
     * tenants to the freshly-created admin.
     */
    public function run(): void
    {
        $acme = Tenant::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme']);
        $globex = Tenant::firstOrCreate(['slug' => 'globex'], ['name' => 'Globex']);

        if ($acme->projects()->count() === 0) {
            Project::factory()->count(5)->state(['tenant_id' => $acme->id])->create();
        }

        if ($globex->projects()->count() === 0) {
            Project::factory()->count(5)->state(['tenant_id' => $globex->id])->create();
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@arqel.test'],
            ['name' => 'Admin', 'password' => Hash::make('password')],
        );

        $admin->tenants()->syncWithoutDetaching([
            $acme->id => ['primary' => true],
            $globex->id => ['primary' => false],
        ]);

        $admin->update(['current_tenant_id' => $acme->id]);
    }
}

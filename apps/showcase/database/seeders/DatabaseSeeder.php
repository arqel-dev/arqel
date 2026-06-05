<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seed a small-but-rich showcase domain:
 *
 * - 2 tenants (Acme, Globex)
 * - ~5 authors, ~10 categories
 * - ~30 posts (each with an author, 1-3 categories, a couple comments),
 *   split across the two tenants via an explicit `tenant_id` state
 * - ~8 tickets with varied workflow states
 * - an admin user attached to both tenants, current tenant = Acme
 *
 * Idempotent: tenants/admin via `firstOrCreate`, and the bulk content is
 * only generated when the tables are still empty, so re-running the
 * seeder does not duplicate rows.
 *
 * NOTE: `Post` uses `BelongsToTenant`, which auto-fills `tenant_id` from
 * the *current* tenant on create. There is no current tenant during
 * seeding, so we always pass `tenant_id` explicitly via factory state.
 * Model events are left enabled so the `Versionable` + `LogsActivity`
 * traits on `Post` are exercised against their published tables.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Tenant::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme']);
        $globex = Tenant::firstOrCreate(['slug' => 'globex'], ['name' => 'Globex']);

        if (Author::query()->count() === 0) {
            Author::factory()->count(5)->create();
        }

        if (Category::query()->count() === 0) {
            Category::factory()->count(10)->create();
        }

        if (Post::query()->withoutGlobalScopes()->count() === 0) {
            /** @var list<int> $authors */
            $authors = Author::query()->pluck('id')->all();
            /** @var list<int> $categories */
            $categories = Category::query()->pluck('id')->all();

            for ($i = 0; $i < 30; $i++) {
                $tenantId = $i % 2 === 0 ? $acme->id : $globex->id;

                $post = Post::factory()
                    ->state([
                        'tenant_id' => $tenantId,
                        'author_id' => fake()->randomElement($authors),
                    ])
                    ->create();

                $post->categories()->attach(
                    fake()->randomElements($categories, fake()->numberBetween(1, 3)),
                );

                Comment::factory()
                    ->count(fake()->numberBetween(1, 3))
                    ->state(['post_id' => $post->id])
                    ->create();
            }
        }

        if (Ticket::query()->count() === 0) {
            Ticket::factory()->count(8)->create();
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

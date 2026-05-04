<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\States\PostStates;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect(['Ada Lovelace', 'Alan Turing', 'Grace Hopper'])
            ->map(static fn (string $name): User => User::create([
                'name' => $name,
                'email' => Str::slug($name).'@arqel.dev',
                'password' => 'demo-password-hash',
            ]));

        $categoryNames = ['Engineering', 'Product', 'Design', 'DevOps', 'Community'];
        foreach ($categoryNames as $name) {
            Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Posts about {$name}.",
            ]);
        }

        $tagNames = [
            'laravel', 'react', 'inertia', 'php', 'typescript', 'tailwind',
            'docker', 'ci', 'shadcn', 'figma', 'api', 'auth',
            'workflow', 'versioning', 'audit', 'ai', 'pest', 'vitest',
            'realtime', 'mcp',
        ];
        $categories = ['frontend', 'backend', 'devops', 'design', 'product'];
        foreach ($tagNames as $i => $name) {
            Tag::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'category' => $categories[$i % count($categories)],
            ]);
        }

        $states = [
            PostStates::DRAFT,
            PostStates::REVIEW,
            PostStates::PUBLISHED,
            PostStates::ARCHIVED,
        ];

        for ($i = 1; $i <= 50; $i++) {
            $state = $states[$i % count($states)];
            $author = $users->random();
            Post::create([
                'title' => "Demo post #{$i}",
                'slug' => "demo-post-{$i}",
                'summary' => "Auto summary placeholder for post #{$i}.",
                'body' => "Body content for post #{$i}. ".str_repeat('Lorem ipsum. ', 10),
                'state' => $state,
                'published_at' => $state === PostStates::PUBLISHED ? now()->subDays($i) : null,
                'author_id' => $author->id,
            ]);
        }
    }
}

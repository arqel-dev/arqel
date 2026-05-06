<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(rand(3, 8));

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'body' => fake()->paragraphs(rand(2, 5), true),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'featured' => fake()->boolean(20),
            'published_at' => fake()->optional(0.7)->dateTimeBetween('-1 year'),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
        ];
    }
}

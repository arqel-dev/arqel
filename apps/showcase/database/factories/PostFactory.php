<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(4, true));
        $status = fake()->randomElement(['draft', 'published', 'archived']);

        return [
            'author_id' => Author::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'body' => fake()->paragraphs(3, true),
            'status' => $status,
            'featured' => fake()->boolean(20),
            'published_at' => $status === 'published' ? fake()->dateTimeThisYear() : null,
            'meta' => ['reading_time' => fake()->numberBetween(1, 12)],
        ];
    }
}

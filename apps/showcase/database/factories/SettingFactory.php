<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Setting>
 */
final class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::slug(fake()->unique()->words(2, true), '.'),
            // Associative map — matches the KeyValueField (asObject) shape.
            'value' => ['enabled' => 'true', 'theme' => 'dark'],
            // List of rows — matches the RepeaterField schema.
            'items' => [
                ['label' => 'Item A', 'content' => '1'],
                ['label' => 'Item B', 'content' => '2'],
            ],
            // List of scalars — matches the TagsField shape.
            'tags' => ['alpha', 'beta'],
            'snippet' => '{"k":"v"}',
            'notes' => '# Notes',
        ];
    }
}

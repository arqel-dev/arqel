<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MediaAsset>
 */
final class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(2, true),
            'file_path' => 'media/'.$this->faker->uuid().'.png',
            'mime' => 'image/png',
            'size' => $this->faker->numberBetween(1024, 1048576),
        ];
    }
}

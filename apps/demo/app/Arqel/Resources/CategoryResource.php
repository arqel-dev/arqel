<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Category;

final class CategoryResource
{
    public static string $model = Category::class;

    public static string $slug = 'categories';

    public static string $label = 'Category';

    public static string $pluralLabel = 'Categories';

    /** @return array<int, array<string, mixed>> */
    public static function fields(): array
    {
        return [
            ['name' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'slug', 'type' => 'slug'],
            ['name' => 'description', 'type' => 'textarea'],
        ];
    }
}

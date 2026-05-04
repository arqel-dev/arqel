<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Tag;

final class TagResource
{
    public static string $model = Tag::class;

    public static string $slug = 'tags';

    public static string $label = 'Tag';

    public static string $pluralLabel = 'Tags';

    /** @return array<int, array<string, mixed>> */
    public static function fields(): array
    {
        return [
            ['name' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'slug', 'type' => 'slug'],
            [
                'name' => 'category',
                'type' => 'aiSelect',
                'options' => ['frontend', 'backend', 'devops', 'design', 'product'],
                'classifyFromFields' => ['name', 'description'],
            ],
        ];
    }
}

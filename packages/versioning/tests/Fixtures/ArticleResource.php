<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

final class ArticleResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = Article::class;

    public static ?string $slug = 'articles';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [];
    }
}

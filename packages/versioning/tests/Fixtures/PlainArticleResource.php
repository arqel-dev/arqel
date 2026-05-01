<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Arqel\Core\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource fixture cujo model NÃO usa `Versionable`. Usado para
 * cobrir o caminho 422 do `VersionHistoryController`.
 */
final class PlainArticleResource extends Resource
{
    /** @var class-string<Model> */
    public static string $model = PlainArticle::class;

    public static ?string $slug = 'plain-articles';

    /**
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [];
    }
}

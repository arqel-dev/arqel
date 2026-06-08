<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Arqel\Versioning\Concerns\Versionable;
use Illuminate\Database\Eloquent\Model;

/**
 * Fixture com cast `array` na coluna `meta` — exercita o caminho
 * cast-aware do snapshot/restore/diff (issue #187). O `Article` base
 * não tem casts, por isso a corrupção de double-encoding passava
 * despercebida.
 *
 * @property int $id
 * @property string $title
 * @property array<string, mixed>|null $meta
 */
final class CastedArticle extends Model
{
    use Versionable;

    protected $table = 'versioning_casted_articles';

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'meta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}

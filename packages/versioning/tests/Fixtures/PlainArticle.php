<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Sibling de `Article` sem o trait `Versionable` — usado para
 * validar que o controller devolve 422 quando o model não é
 * versionável.
 *
 * @property int $id
 * @property string $title
 */
final class PlainArticle extends Model
{
    protected $table = 'versioning_plain_articles';

    /**
     * @var list<string>
     */
    protected $fillable = ['title'];
}

<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Arqel\Versioning\Concerns\Versionable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property string $status
 */
final class Article extends Model
{
    use Versionable;

    protected $table = 'versioning_articles';

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'body', 'status'];
}

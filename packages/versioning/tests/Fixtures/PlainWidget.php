<?php

declare(strict_types=1);

namespace Arqel\Versioning\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 */
final class PlainWidget extends Model
{
    protected $table = 'plain_widgets';

    /** @var list<string> */
    protected $fillable = ['name'];
}

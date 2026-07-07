<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class RelTag extends Model
{
    protected $table = 'rel_tags';

    protected $guarded = [];

    public $timestamps = false;
}

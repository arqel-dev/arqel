<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class RelComment extends Model
{
    protected $table = 'rel_comments';

    protected $guarded = [];

    public $timestamps = false;
}

<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\GlobalSearch;

use Illuminate\Database\Eloquent\Model;

class RsPerson extends Model
{
    protected $table = 'rs_people';

    protected $guarded = [];

    public $timestamps = false;
}

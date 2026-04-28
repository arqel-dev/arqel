<?php

declare(strict_types=1);

namespace Arqel\Fields\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class StubModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

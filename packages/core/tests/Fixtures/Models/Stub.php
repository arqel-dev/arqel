<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Non-final model used by Mockery-driven lifecycle tests.
 */
class Stub extends Model
{
    protected $guarded = [];
}

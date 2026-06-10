<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Soft-deletable model used by the restore-route coverage (#244). The
 * SoftDeletes trait installs the global scope that hides trashed rows from
 * the default query — exactly the scope the restore path must bypass with
 * `withTrashed()`.
 */
class SoftStub extends Model
{
    use SoftDeletes;

    protected $table = 'soft_stubs';

    protected $guarded = [];
}

<?php

declare(strict_types=1);

namespace Arqel\Tenant\Tests\Fixtures;

use Arqel\Tenant\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Test-only model exercising the BelongsToTenant trait. Used by
 * the scope and trait tests to verify auto-filling, scope
 * application, and query scope helpers.
 */
class TenantedPost extends Model
{
    use BelongsToTenant;

    protected $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}

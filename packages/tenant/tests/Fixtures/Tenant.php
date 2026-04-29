<?php

declare(strict_types=1);

namespace Arqel\Tenant\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Test-only Eloquent model used by the resolver tests. Kept
 * non-final so test cases can extend it (e.g. to short-circuit
 * `query()` for DB-less assertions).
 *
 * No migrations: tests never persist; we only need attribute
 * carrying and `instanceof` checks against the model class.
 */
class Tenant extends Model
{
    protected $table = 'tenants';

    protected $guarded = [];

    public $timestamps = false;
}

<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model with a non-default primary key. Used to prove bulk actions
 * resolve records by the model's real key name (#69) rather than a
 * hardcoded `id` column.
 */
class CustomKeyStub extends Model
{
    protected $table = 'custom_key_stubs';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}

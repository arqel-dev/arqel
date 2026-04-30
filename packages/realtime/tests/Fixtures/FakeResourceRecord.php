<?php

declare(strict_types=1);

namespace Arqel\Realtime\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $id
 * @property string|null $name
 */
final class FakeResourceRecord extends Model
{
    protected $table = 'fake_resource_records';

    protected $guarded = [];

    public $timestamps = true;
}

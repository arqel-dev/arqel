<?php

declare(strict_types=1);

namespace Arqel\Table\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A SoftDeletes-enabled model used by the TrashedFilter tests.
 */
final class TrashableUser extends Model
{
    use SoftDeletes;

    protected $table = 'trashable_users';

    /** @var list<string> */
    protected $fillable = ['name'];
}

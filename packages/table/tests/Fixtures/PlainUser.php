<?php

declare(strict_types=1);

namespace Arqel\Table\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A plain model (no SoftDeletes) used to assert TrashedFilter is a
 * harmless no-op when the underlying model is not soft-deletable.
 */
final class PlainUser extends Model
{
    protected $table = 'plain_users';

    /** @var list<string> */
    protected $fillable = ['name'];
}

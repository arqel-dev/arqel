<?php

declare(strict_types=1);

namespace Arqel\Import\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class ImportUser extends Model
{
    protected $table = 'import_users';

    protected $guarded = [];

    public $timestamps = false;
}

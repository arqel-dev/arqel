<?php

declare(strict_types=1);

namespace Arqel\Audit\Tests\Fixtures;

use Arqel\Audit\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $secret
 */
final class FakeAuditableModel extends Model
{
    use LogsActivity;

    protected $table = 'fake_auditable_models';

    /** @var list<string> */
    protected $fillable = ['name', 'email'];
}

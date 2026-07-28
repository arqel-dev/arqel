<?php

declare(strict_types=1);

namespace Arqel\Export\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted record of a generated export, binding a file to its owner.
 *
 * `owner_user_id` is stored as a plain string (the stringified
 * `auth()->id()`) so the package stays decoupled from the app's User
 * model — there is deliberately no `belongsTo(User)` relation.
 *
 * @internal Esta classe é interna ao Arqel (ADR-019) e pode mudar em qualquer minor.
 */
final class Export extends Model
{
    protected $table = 'arqel_exports';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}

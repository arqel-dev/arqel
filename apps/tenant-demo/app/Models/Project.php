<?php

declare(strict_types=1);

namespace App\Models;

use Arqel\Tenant\Concerns\BelongsToTenant;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Project extends Model
{
    // Provides the `tenant_id` global scope, auto-fill on create, and a
    // `tenant()` BelongsTo relation resolved from `arqel.tenancy.model`.
    use BelongsToTenant;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['name', 'status', 'tenant_id'];

    /** @var array<string, string> */
    protected $casts = [
        'status' => 'string',
    ];
}

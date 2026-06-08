<?php

declare(strict_types=1);

namespace App\Models;

use Arqel\Tenant\Concerns\BelongsToTenant;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class MediaAsset extends Model
{
    // Tenant scoping + auto-fill of tenant_id on create.
    use BelongsToTenant;

    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'title', 'file_path', 'mime', 'size'];
}

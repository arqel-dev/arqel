<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Histórico de versões de um plugin (MKTPLC-001).
 *
 * Cada release semver vira uma row append-only. `Plugin::latest_version`
 * é um cache desnormalizado para queries rápidas no listing.
 *
 * @property int $id
 * @property int $plugin_id
 * @property string $version
 * @property string|null $changelog
 * @property Carbon $released_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PluginVersion extends Model
{
    protected $table = 'arqel_plugin_versions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_id',
        'version',
        'changelog',
        'released_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plugin, $this>
     */
    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }

    /**
     * @return HasMany<PluginInstallation, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(PluginInstallation::class);
    }
}

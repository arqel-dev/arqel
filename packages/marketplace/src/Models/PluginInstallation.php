<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tracking de instalação anônimo de um plugin (MKTPLC-001).
 *
 * Tabela append-only — não usa `created_at`/`updated_at` automáticos;
 * o timestamp efetivo é `installed_at`. `anonymized_user_hash` é
 * opcional e tipicamente derivado de `hash('sha256', $user_id . secret)`.
 *
 * @property int $id
 * @property int $plugin_id
 * @property int|null $plugin_version_id
 * @property Carbon $installed_at
 * @property string|null $anonymized_user_hash
 * @property array<string, mixed>|null $context
 */
final class PluginInstallation extends Model
{
    protected $table = 'arqel_plugin_installations';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_id',
        'plugin_version_id',
        'installed_at',
        'anonymized_user_hash',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'installed_at' => 'datetime',
            'context' => 'array',
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
     * @return BelongsTo<PluginVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PluginVersion::class, 'plugin_version_id');
    }
}

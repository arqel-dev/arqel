<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Review (1-5 estrelas + comentário) de um plugin (MKTPLC-001).
 *
 * Idempotência por `(user_id, plugin_id)` é garantida no controller
 * via `firstOrCreate` — não há unique index na tabela porque
 * `user_id` é nullable (reviews anônimas são permitidas).
 *
 * @property int $id
 * @property int $plugin_id
 * @property int|null $user_id
 * @property int $stars
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PluginReview extends Model
{
    protected $table = 'arqel_plugin_reviews';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'plugin_id',
        'user_id',
        'stars',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stars' => 'integer',
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
     * Reviews positivas (≥ 4 estrelas).
     *
     * @param Builder<PluginReview> $query
     *
     * @return Builder<PluginReview>
     */
    public function scopePositive(Builder $query): Builder
    {
        return $query->where('stars', '>=', 4);
    }
}

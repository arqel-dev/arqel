<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Review (1-5 estrelas + comentário) de um plugin (MKTPLC-001).
 *
 * MKTPLC-006 estende a model com helpful/unhelpful counters, status de
 * moderação (`pending`/`published`/`hidden`), `verified_purchaser` flag
 * e relação `votes()` para `PluginReviewVote`.
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
 * @property bool $verified_purchaser
 * @property int $helpful_count
 * @property int $unhelpful_count
 * @property string $status
 * @property string|null $moderation_reason
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
        'verified_purchaser',
        'helpful_count',
        'unhelpful_count',
        'status',
        'moderation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'verified_purchaser' => 'boolean',
            'helpful_count' => 'integer',
            'unhelpful_count' => 'integer',
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
     * @return HasMany<PluginReviewVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PluginReviewVote::class, 'review_id');
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

    /**
     * @param Builder<PluginReview> $query
     *
     * @return Builder<PluginReview>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * @param Builder<PluginReview> $query
     *
     * @return Builder<PluginReview>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param Builder<PluginReview> $query
     *
     * @return Builder<PluginReview>
     */
    public function scopeHidden(Builder $query): Builder
    {
        return $query->where('status', 'hidden');
    }

    /**
     * Ordena por `helpful_count` desc, depois pelo "score" (helpful − unhelpful)
     * desc, depois pelo id desc para tiebreak determinístico.
     *
     * @param Builder<PluginReview> $query
     *
     * @return Builder<PluginReview>
     */
    public function scopeMostHelpful(Builder $query): Builder
    {
        return $query
            ->orderByDesc('helpful_count')
            ->orderByRaw('(helpful_count - unhelpful_count) desc')
            ->orderByDesc('id');
    }

    /**
     * @param Builder<PluginReview> $query
     *
     * @return Builder<PluginReview>
     */
    public function scopeMostRecent(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @param Builder<PluginReview> $query
     *
     * @return Builder<PluginReview>
     */
    public function scopeHighestRated(Builder $query): Builder
    {
        return $query->orderByDesc('stars')->orderByDesc('id');
    }
}

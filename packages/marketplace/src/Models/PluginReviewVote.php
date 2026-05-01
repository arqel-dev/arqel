<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Voto de helpful/unhelpful em uma review (MKTPLC-006).
 *
 * Unique `(review_id, user_id)` garante que cada user vota apenas uma vez
 * por review; a re-submissão atualiza o tipo do voto via `firstOrCreate`
 * + update no controller.
 *
 * @property int $id
 * @property int $review_id
 * @property int $user_id
 * @property string $vote
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PluginReviewVote extends Model
{
    protected $table = 'arqel_plugin_review_votes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'review_id',
        'user_id',
        'vote',
    ];

    /**
     * @return BelongsTo<PluginReview, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(PluginReview::class, 'review_id');
    }

    /**
     * Relação defensiva para o user que votou — retorna `BelongsTo` cru
     * porque o pacote não conhece a model `User` da app host.
     *
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model', Model::class);

        return $this->belongsTo($model, 'user_id');
    }
}

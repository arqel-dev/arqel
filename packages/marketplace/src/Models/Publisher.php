<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Publisher profile no marketplace (MKTPLC-004-publisher).
 *
 * Representa a entidade pública por trás de um conjunto de plugins. Pode estar
 * associada a um usuário (`user_id`), mas não é obrigatório — a relação principal
 * é via `arqel_plugins.publisher_id`.
 *
 * @property int $id
 * @property string $slug
 * @property int|null $user_id
 * @property string $name
 * @property string|null $bio
 * @property string|null $avatar_url
 * @property string|null $website_url
 * @property string|null $github_url
 * @property string|null $twitter_handle
 * @property bool $verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Publisher extends Model
{
    protected $table = 'arqel_publishers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'user_id',
        'name',
        'bio',
        'avatar_url',
        'website_url',
        'github_url',
        'twitter_handle',
        'verified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Plugin, $this>
     */
    public function plugins(): HasMany
    {
        return $this->hasMany(Plugin::class, 'publisher_id');
    }

    /**
     * Restringe a publishers verificados.
     *
     * @param Builder<Publisher> $query
     *
     * @return Builder<Publisher>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verified', true);
    }

    /**
     * Apenas publishers que têm pelo menos um plugin published.
     *
     * @param Builder<Publisher> $query
     *
     * @return Builder<Publisher>
     */
    public function scopeWithPlugins(Builder $query): Builder
    {
        return $query->whereHas('plugins', static function (Builder $sub): void {
            $sub->where('status', 'published');
        });
    }

    /**
     * Stats agregados sobre os plugins published do publisher.
     *
     * @return array{plugins_count: int, total_downloads: int, avg_rating: float}
     */
    public function aggregateStats(): array
    {
        $pluginIds = $this->plugins()
            ->where('status', 'published')
            ->pluck('id')
            ->all();

        $count = count($pluginIds);

        if ($count === 0) {
            return [
                'plugins_count' => 0,
                'total_downloads' => 0,
                'avg_rating' => 0.0,
            ];
        }

        $totalDownloads = (int) PluginInstallation::query()
            ->whereIn('plugin_id', $pluginIds)
            ->count();

        $avgRating = (float) PluginReview::query()
            ->whereIn('plugin_id', $pluginIds)
            ->where('status', 'published')
            ->avg('stars');

        return [
            'plugins_count' => $count,
            'total_downloads' => $totalDownloads,
            'avg_rating' => round($avgRating, 2),
        ];
    }
}

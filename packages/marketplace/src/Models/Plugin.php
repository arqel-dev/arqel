<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Plugin publicado no marketplace (MKTPLC-001).
 *
 * Status state-machine: draft → pending → published → archived.
 * Apenas plugins `published` são expostos publicamente via API REST.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property string $type
 * @property int|null $author_id
 * @property string|null $composer_package
 * @property string|null $npm_package
 * @property string $github_url
 * @property string $license
 * @property array<int, string>|null $screenshots
 * @property string|null $latest_version
 * @property string $status
 * @property array<string, mixed>|null $submission_metadata
 * @property int|null $submitted_by_user_id
 * @property Carbon|null $submitted_at
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Plugin extends Model
{
    protected $table = 'arqel_plugins';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'type',
        'author_id',
        'composer_package',
        'npm_package',
        'github_url',
        'license',
        'screenshots',
        'latest_version',
        'status',
        'submission_metadata',
        'submitted_by_user_id',
        'submitted_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'screenshots' => 'array',
            'status' => 'string',
            'submission_metadata' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PluginVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(PluginVersion::class);
    }

    /**
     * @return HasMany<PluginInstallation, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(PluginInstallation::class);
    }

    /**
     * @return HasMany<PluginReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PluginReview::class);
    }

    /**
     * Restringe a query a plugins publicly visíveis.
     *
     * @param Builder<Plugin> $query
     *
     * @return Builder<Plugin>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    /**
     * Filtra por tipo (`field`, `widget`, `integration`, `theme`).
     *
     * @param Builder<Plugin> $query
     *
     * @return Builder<Plugin>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Busca textual em `name` e `description` (LIKE wildcards).
     *
     * @param Builder<Plugin> $query
     *
     * @return Builder<Plugin>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(static function (Builder $sub) use ($like): void {
            $sub->where('name', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }
}

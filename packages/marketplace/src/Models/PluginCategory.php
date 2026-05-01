<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Categoria de plugins (MKTPLC-007).
 *
 * Hierarquia opcional via `parent_id` self-referencing. Cada categoria pode ter
 * múltiplos plugins associados via pivot `arqel_plugin_category_assignments`.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class PluginCategory extends Model
{
    protected $table = 'arqel_plugin_categories';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
        'parent_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'parent_id' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Plugin, $this>
     */
    public function plugins(): BelongsToMany
    {
        return $this->belongsToMany(
            Plugin::class,
            'arqel_plugin_category_assignments',
            'category_id',
            'plugin_id',
        );
    }

    /**
     * @return BelongsTo<PluginCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<PluginCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Categorias raiz (sem parent).
     *
     * @param Builder<PluginCategory> $query
     *
     * @return Builder<PluginCategory>
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Ordenadas por `sort_order` asc.
     *
     * @param Builder<PluginCategory> $query
     *
     * @return Builder<PluginCategory>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}

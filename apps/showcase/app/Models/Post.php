<?php

declare(strict_types=1);

namespace App\Models;

use Arqel\Audit\Concerns\LogsActivity;
use Arqel\Tenant\Concerns\BelongsToTenant;
use Arqel\Versioning\Concerns\Versionable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

final class Post extends Model
{
    // Tenant scoping + auto-fill of tenant_id on create.
    use BelongsToTenant;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    // Spatie activity log with Arqel defaults (logs the fillable attributes).
    use LogsActivity;

    // Append-only Eloquent snapshot versioning on create/update.
    use Versionable;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'slug',
        'body',
        'status',
        'featured',
        'published_at',
        'meta',
        'author_id',
        'tenant_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}

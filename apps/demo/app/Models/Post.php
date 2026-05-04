<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasWorkflow;
use App\Models\Concerns\Versionable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $body
 * @property string $state
 * @property DateTimeInterface|null $published_at
 * @property int|null $author_id
 */
final class Post extends Model
{
    use HasWorkflow;
    use Versionable;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'body',
        'state',
        'published_at',
        'author_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'published_at' => 'datetime',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'state' => 'draft',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag');
    }
}

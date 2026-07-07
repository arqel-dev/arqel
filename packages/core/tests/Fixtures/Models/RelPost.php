<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RelPost extends Model
{
    protected $table = 'rel_posts';

    protected $guarded = [];

    public $timestamps = false;

    public function comments(): HasMany
    {
        return $this->hasMany(RelComment::class, 'post_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RelTag::class, 'rel_post_tag', 'post_id', 'tag_id');
    }
}

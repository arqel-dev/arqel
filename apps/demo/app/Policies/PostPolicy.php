<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

final class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->author_id === $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->author_id === $user->id;
    }
}

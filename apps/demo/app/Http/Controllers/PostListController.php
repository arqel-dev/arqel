<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Arqel\Resources\PostResource;
use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

final class PostListController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Posts/Index', [
            'fields' => PostResource::fields(),
            'posts' => Post::query()->orderByDesc('id')->get(),
        ]);
    }
}

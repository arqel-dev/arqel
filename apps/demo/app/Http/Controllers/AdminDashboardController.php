<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Arqel\Panel;
use App\Models\Post;
use Inertia\Inertia;
use Inertia\Response;

final class AdminDashboardController
{
    public function __invoke(): Response
    {
        $panel = Panel::get('admin');

        return Inertia::render('Admin/Dashboard', [
            'panel' => [
                'id' => $panel?->id,
                'resources' => $panel?->resources ?? [],
            ],
            'stats' => [
                'posts' => Post::query()->count(),
                'published' => Post::query()->where('state', 'published')->count(),
                'draft' => Post::query()->where('state', 'draft')->count(),
            ],
        ]);
    }
}

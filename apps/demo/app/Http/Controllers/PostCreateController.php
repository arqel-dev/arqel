<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Arqel\Resources\PostResource;
use Inertia\Inertia;
use Inertia\Response;

final class PostCreateController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Posts/Create', [
            'fields' => PostResource::fields(),
        ]);
    }
}

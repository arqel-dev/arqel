<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Arqel\Resources\TagResource;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

final class TagListController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Tags/Index', [
            'fields' => TagResource::fields(),
            'tags' => Tag::query()->orderBy('name')->get(),
        ]);
    }
}

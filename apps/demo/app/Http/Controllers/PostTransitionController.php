<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\States\PostStates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PostTransitionController
{
    public function __invoke(Request $request, int $postId): JsonResponse
    {
        $post = Post::query()->findOrFail($postId);
        $next = (string) $request->input('to', '');
        $allowed = PostStates::allowedFrom((string) $post->state);

        $ok = $post->transitionTo($next, $allowed);

        return new JsonResponse([
            'ok' => $ok,
            'state' => $post->state,
            'allowed' => $allowed,
        ], $ok ? 200 : 422);
    }
}

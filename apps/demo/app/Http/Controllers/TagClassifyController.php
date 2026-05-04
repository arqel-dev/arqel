<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint demo para `Field::aiSelect()->classifyFromFields(...)`.
 */
final class TagClassifyController
{
    public function __invoke(Request $request): JsonResponse
    {
        $name = strtolower((string) $request->input('name', ''));

        $category = match (true) {
            str_contains($name, 'react'), str_contains($name, 'css'), str_contains($name, 'ui') => 'frontend',
            str_contains($name, 'php'), str_contains($name, 'laravel'), str_contains($name, 'api') => 'backend',
            str_contains($name, 'docker'), str_contains($name, 'ci'), str_contains($name, 'deploy') => 'devops',
            str_contains($name, 'figma'), str_contains($name, 'design') => 'design',
            default => 'product',
        };

        return new JsonResponse([
            'category' => $category,
            'confidence' => 0.85,
        ]);
    }
}

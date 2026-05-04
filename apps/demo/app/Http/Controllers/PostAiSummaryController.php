<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint demo para `Field::aiText('summary')`.
 *
 * Em produção delega ao `arqel-dev/ai` provider; aqui retorna stub determinístico
 * para que o demo funcione offline e seja testável sem chave de API.
 */
final class PostAiSummaryController
{
    public function __invoke(Request $request): JsonResponse
    {
        $title = (string) $request->input('title', '');
        $body = (string) $request->input('body', '');

        $summary = trim(sprintf(
            '%s — auto summary based on %d body chars.',
            $title !== '' ? $title : 'Untitled',
            strlen($body),
        ));

        return new JsonResponse([
            'summary' => $summary,
            'provider' => 'arqel-ai-stub',
        ]);
    }
}

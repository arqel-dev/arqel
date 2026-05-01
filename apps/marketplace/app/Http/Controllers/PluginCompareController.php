<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SeoData;
use Arqel\Marketplace\Models\Plugin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Compare side-by-side de até 3 plugins (MKTPLC-004-compare).
 *
 * Aceita `?slugs=foo,bar,baz` (mín. 2, máx. 3). Slugs não encontrados
 * (ou em status diferente de `published`) são reportados em `notFound`
 * em vez de gerar 404 — melhor UX quando o usuário compartilha link.
 */
final class PluginCompareController
{
    public function __invoke(Request $request): Response
    {
        $rawSlugs = (string) $request->query('slugs', '');

        $slugs = collect(explode(',', $rawSlugs))
            ->map(static fn (string $slug): string => trim($slug))
            ->filter(static fn (string $slug): bool => $slug !== '')
            ->unique()
            ->values()
            ->all();

        if (count($slugs) < 2 || count($slugs) > 3) {
            throw new HttpException(422, 'compare requires between 2 and 3 plugin slugs');
        }

        $plugins = Plugin::query()
            ->published()
            ->withCount('reviews')
            ->whereIn('slug', $slugs)
            ->get();

        $foundSlugs = $plugins->pluck('slug')->all();
        $notFound = array_values(array_diff($slugs, $foundSlugs));

        // Preserva ordem solicitada na query string.
        $ordered = collect($slugs)
            ->map(static fn (string $slug) => $plugins->firstWhere('slug', $slug))
            ->filter()
            ->values();

        $names = $ordered->pluck('name')->filter()->all();
        $title = count($names) > 0
            ? 'Comparar '.implode(' vs ', $names).' — Arqel Marketplace'
            : 'Comparar plugins — Arqel Marketplace';

        View::share('seo', new SeoData(
            title: $title,
            description: 'Comparação side-by-side de plugins do Arqel Marketplace — preço, downloads, estrelas, licença e mais.',
        ));

        return Inertia::render('Marketplace/Compare', [
            'plugins' => $ordered,
            'notFound' => $notFound,
        ]);
    }
}

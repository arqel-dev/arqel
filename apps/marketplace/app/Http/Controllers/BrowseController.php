<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SeoData;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse page com grid de plugins, filtros (type/category) e paginação.
 */
final class BrowseController
{
    public function __invoke(Request $request): Response
    {
        $type = $request->string('type')->trim()->value() ?: null;
        $categorySlug = $request->string('category')->trim()->value() ?: null;

        $plugins = Plugin::query()
            ->published()
            ->when($type !== null, fn ($q) => $q->ofType($type))
            ->when($categorySlug !== null, function ($q) use ($categorySlug): void {
                $q->whereHas('categories', fn ($cq) => $cq->where('slug', $categorySlug));
            })
            ->orderByDesc('featured')
            ->orderByDesc('trending_score')
            ->paginate(20)
            ->withQueryString();

        $categoryName = null;
        if ($categorySlug !== null) {
            $categoryName = PluginCategory::query()->where('slug', $categorySlug)->value('name');
        }

        $description = match (true) {
            $categoryName !== null && $type !== null => "Browse plugins {$type} na categoria {$categoryName} — Arqel Marketplace.",
            $categoryName !== null => "Browse plugins na categoria {$categoryName} — Arqel Marketplace.",
            $type !== null => "Browse plugins do tipo {$type} — Arqel Marketplace.",
            default => 'Browse todos os plugins disponíveis no Arqel Marketplace — fields, widgets, integrações e themes.',
        };

        View::share('seo', new SeoData(
            title: 'Browse plugins — Arqel Marketplace',
            description: $description,
        ));

        return Inertia::render('Marketplace/Browse', [
            'plugins' => $plugins,
            'categories' => PluginCategory::query()->root()->ordered()->get(),
            'filters' => [
                'type' => $type,
                'category' => $categorySlug,
            ],
        ]);
    }
}

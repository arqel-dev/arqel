<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\SeoData;
use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\PluginPurchase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plugin detail page — header + tabs (README/Versions/Reviews) + sidebar (install command, github).
 *
 * Apenas slugs com status `published` são acessíveis publicamente; slug inexistente ou
 * em outro status retorna 404.
 */
final class PluginDetailController
{
    public function __invoke(string $slug): Response
    {
        $plugin = Plugin::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $versions = $plugin->versions()
            ->orderByDesc('released_at')
            ->limit(20)
            ->get();

        $reviews = $plugin->reviews()
            ->latest()
            ->paginate(10);

        $related = Plugin::query()
            ->published()
            ->ofType($plugin->type)
            ->where('id', '!=', $plugin->id)
            ->limit(5)
            ->get();

        $hasPurchase = false;
        if (Auth::check()) {
            $authId = Auth::id();
            $userId = is_numeric($authId) ? (int) $authId : 0;

            if ($userId !== 0) {
                $hasPurchase = PluginPurchase::query()
                    ->where('plugin_id', $plugin->id)
                    ->where('buyer_user_id', $userId)
                    ->where('status', 'completed')
                    ->exists();
            }
        }

        $screenshots = $plugin->screenshots ?? [];
        $ogImage = is_array($screenshots) && count($screenshots) > 0 && is_string($screenshots[0])
            ? $screenshots[0]
            : '/images/og/marketplace-default.png';

        $price = $plugin->price_cents > 0
            ? number_format($plugin->price_cents / 100, 2, '.', '')
            : '0.00';

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $plugin->name,
            'description' => $plugin->description,
            'brand' => [
                '@type' => 'Brand',
                'name' => $plugin->publisher?->name ?? 'Arqel',
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => $price,
                'priceCurrency' => $plugin->currency !== '' ? $plugin->currency : 'USD',
                'availability' => 'https://schema.org/InStock',
                'url' => url('/plugins/'.$plugin->slug),
            ],
        ];

        View::share('seo', new SeoData(
            title: "{$plugin->name} — Arqel Marketplace",
            description: SeoData::truncate($plugin->description, 160),
            ogImage: $ogImage,
            ogType: 'product',
            canonical: url('/plugins/'.$plugin->slug),
            jsonLd: $jsonLd,
        ));

        return Inertia::render('Marketplace/PluginDetail', [
            'plugin' => $plugin,
            'versions' => $versions,
            'reviews' => $reviews,
            'related' => $related,
            'has_purchase' => $hasPurchase,
        ]);
    }
}

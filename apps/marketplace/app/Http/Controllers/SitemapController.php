<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Arqel\Marketplace\Models\Plugin;
use Arqel\Marketplace\Models\Publisher;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Gera sitemap.xml dinâmico para SEO (MKTPLC-004-ssr).
 *
 * Inclui rotas estáticas (landing, browse), todos os plugins com status `published`
 * e todos os publishers conhecidos. Cache de 1 hora via `cache()->remember()` para
 * evitar pressão no banco em crawlers agressivos (GoogleBot, BingBot).
 */
final class SitemapController
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('marketplace:sitemap', now()->addHour(), static function (): string {
            $base = rtrim((string) config('app.url', 'https://marketplace.arqel.dev'), '/');
            $entries = [];

            $entries[] = self::entry($base.'/', null, 'daily', '1.0');
            $entries[] = self::entry($base.'/browse', null, 'daily', '0.9');

            Plugin::query()
                ->where('status', 'published')
                ->orderBy('id')
                ->chunk(500, static function ($plugins) use (&$entries, $base): void {
                    foreach ($plugins as $plugin) {
                        $entries[] = self::entry(
                            $base.'/plugins/'.$plugin->slug,
                            $plugin->updated_at?->toIso8601String(),
                            'weekly',
                            '0.8',
                        );
                    }
                });

            Publisher::query()
                ->orderBy('id')
                ->chunk(500, static function ($publishers) use (&$entries, $base): void {
                    foreach ($publishers as $publisher) {
                        $entries[] = self::entry(
                            $base.'/publishers/'.$publisher->slug,
                            $publisher->updated_at?->toIso8601String(),
                            'weekly',
                            '0.6',
                        );
                    }
                });

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
                .implode("\n", $entries)."\n"
                .'</urlset>';
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    private static function entry(string $loc, ?string $lastmod, string $changefreq, string $priority): string
    {
        $parts = ['  <url>', '    <loc>'.htmlspecialchars($loc, ENT_XML1).'</loc>'];
        if ($lastmod !== null) {
            $parts[] = '    <lastmod>'.htmlspecialchars($lastmod, ENT_XML1).'</lastmod>';
        }
        $parts[] = '    <changefreq>'.$changefreq.'</changefreq>';
        $parts[] = '    <priority>'.$priority.'</priority>';
        $parts[] = '  </url>';

        return implode("\n", $parts);
    }
}

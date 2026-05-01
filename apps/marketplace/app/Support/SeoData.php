<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Value object com metadados SEO + Open Graph + Twitter Card + JSON-LD.
 *
 * Renderizado server-side em `app.blade.php` (para crawlers/social shares) e
 * espelhado client-side via `<MetaTags />` (para navegação Inertia subsequente).
 */
final class SeoData
{
    /**
     * @param array<string, mixed>|null $jsonLd
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $ogImage = null,
        public readonly string $ogType = 'website',
        public readonly string $twitterCard = 'summary_large_image',
        public readonly ?string $canonical = null,
        public readonly ?array $jsonLd = null,
    ) {
    }

    /**
     * Trunca a descrição em N chars (default 160) preservando palavras inteiras.
     */
    public static function truncate(string $value, int $max = 160): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        if (mb_strlen($clean) <= $max) {
            return $clean;
        }

        $cut = mb_substr($clean, 0, $max - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return $cut.'…';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'og_image' => $this->ogImage,
            'og_type' => $this->ogType,
            'twitter_card' => $this->twitterCard,
            'canonical' => $this->canonical,
            'json_ld' => $this->jsonLd,
        ];
    }
}

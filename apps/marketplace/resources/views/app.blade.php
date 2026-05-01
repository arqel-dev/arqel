@php
    /** @var \App\Support\SeoData|null $seo */
    $seo = $seo ?? null;
    $defaultImage = '/images/og/marketplace-default.png';
    $ogImage = $seo?->ogImage ?? $defaultImage;
    $title = $seo?->title ?? 'Arqel Marketplace';
    $description = $seo?->description ?? 'Plugins community para estender seu admin panel Arqel.';
    $ogType = $seo?->ogType ?? 'website';
    $twitterCard = $seo?->twitterCard ?? 'summary_large_image';
    $canonical = $seo?->canonical;
    $jsonLd = $seo?->jsonLd;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ $title }}</title>

    {{-- SEO + Open Graph + Twitter Card (server-rendered para crawlers e social shares) --}}
    <meta name="description" content="{{ $description }}" />
    <meta property="og:site_name" content="Arqel Marketplace" />
    <meta property="og:locale" content="pt_BR" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ $description }}" />
    <meta property="og:type" content="{{ $ogType }}" />
    <meta property="og:image" content="{{ $ogImage }}" />
    <meta name="twitter:card" content="{{ $twitterCard }}" />
    <meta name="twitter:site" content="@arqel" />
    <meta name="twitter:title" content="{{ $title }}" />
    <meta name="twitter:description" content="{{ $description }}" />
    <meta name="twitter:image" content="{{ $ogImage }}" />
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}" />
    @endif
    @if ($jsonLd)
        <script type="application/ld+json">{!! str_replace('<', '<', json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !!}</script>
    @endif

    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="bg-neutral-50 text-neutral-900 antialiased">
    @inertia
</body>
</html>

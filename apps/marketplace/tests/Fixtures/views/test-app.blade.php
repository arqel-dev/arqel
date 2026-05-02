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
    <title inertia>{{ $title }}</title>
    <meta name="description" content="{{ $description }}" />
    <meta property="og:site_name" content="Arqel Marketplace" />
    <meta property="og:locale" content="pt_BR" />
    <meta property="og:title" content="{{ $title }}" />
    <meta property="og:description" content="{{ $description }}" />
    <meta property="og:type" content="{{ $ogType }}" />
    <meta property="og:image" content="{{ $ogImage }}" />
    <meta name="twitter:card" content="{{ $twitterCard }}" />
    <meta name="twitter:title" content="{{ $title }}" />
    <meta name="twitter:description" content="{{ $description }}" />
    <meta name="twitter:image" content="{{ $ogImage }}" />
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}" />
    @endif
    @if ($jsonLd)
        <script type="application/ld+json">{!! str_replace('<', '<', json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !!}</script>
    @endif
    @inertiaHead
</head>
<body>@inertia</body>
</html>

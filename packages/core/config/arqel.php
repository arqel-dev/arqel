<?php

declare(strict_types=1);

return [
    'path' => '/admin',

    'resources' => [
        'path' => app_path('Arqel/Resources'),
        'namespace' => 'App\\Arqel\\Resources',
    ],

    'auth' => [
        'guard' => 'web',
    ],

    'inertia' => [
        'root_view' => 'arqel::app',

        // Vite entry points injected by the `arqel::app` Blade root
        // view. Override when the app uses non-default paths (e.g.
        // a separate admin bundle or a Turbopack setup).
        'vite_entries' => [
            'resources/css/app.css',
            'resources/js/app.tsx',
        ],
    ],

    // Laravel Cloud auto-configure (LCLOUD-002).
    //
    // Quando a app roda em Laravel Cloud, o `CloudConfigurator` ajusta
    // drivers de filesystem/cache/queue/session/broadcasting/logging
    // para os valores recomendados pela plataforma. O comportamento é
    // opt-in via env (`LARAVEL_CLOUD=true`) e pode ser desabilitado
    // explicitamente com `ARQEL_CLOUD_AUTO_CONFIGURE=false`.
    'cloud' => [
        'enabled' => env('LARAVEL_CLOUD', false),
        'auto_configure' => env('ARQEL_CLOUD_AUTO_CONFIGURE', true),
    ],

    // Telemetry / observability (opt-in).
    //
    // Quando `enabled = true`, o `AutoInstrumentation` é registrado
    // como listener para eventos internos do Arqel (workflow, AI) e
    // contadores ficam disponíveis via `MetricsCollector`.
    //
    // Quando `metrics_endpoint_enabled = true`, o endpoint
    // `GET <metrics_endpoint_path>` exporta métricas no formato
    // Prometheus (gated por `web` + `auth` + Gate `viewMetrics`).
    // Internationalization (i18n).
    //
    // O `TranslationLoader` agrega os ficheiros de lang
    // publicados em `resources/lang/{locale}/` para serem
    // injectados como Inertia shared prop (`i18n`). O middleware
    // `SetLocaleMiddleware` lê session/cookie/Accept-Language
    // e chama `App::setLocale()` antes do Inertia partilhar o
    // payload.
    'i18n' => [
        'enabled' => env('ARQEL_I18N_ENABLED', true),
        'default' => env('ARQEL_I18N_DEFAULT', 'en'),
        'locales' => ['en', 'pt_BR'],
    ],

    'telemetry' => [
        'enabled' => env('ARQEL_TELEMETRY_ENABLED', false),
        'metrics_endpoint_enabled' => env('ARQEL_METRICS_ENDPOINT_ENABLED', false),
        'metrics_endpoint_path' => env('ARQEL_METRICS_ENDPOINT_PATH', '/admin/_metrics'),
    ],
];

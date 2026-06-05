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

    // Multi-tenancy (arqel-dev/tenant). AuthUserResolver reads the active
    // tenant from the user's `currentTenant` relation (`relation`) and
    // enumerates the switchable set from the user's `tenants` relation
    // (the resolver's `available_relation` default).
    //
    // Two distinct columns — do NOT conflate:
    //   foreign_key    'tenant_id'         BelongsToTenant FK on tenant-OWNED rows (projects)
    //   switch_column  'current_tenant_id' column on the USER that holds the active tenant
    'tenancy' => [
        'enabled' => true,
        'resolver' => Arqel\Tenant\Resolvers\AuthUserResolver::class,
        'model' => App\Models\Tenant::class,
        'identifier_column' => 'slug',
        'relation' => 'currentTenant',
        'foreign_key' => 'tenant_id',
        'switch_column' => 'current_tenant_id',
    ],

    // Panel-wide middleware. Resolved before boot, so it reliably applies
    // to the admin resource routes (unlike Panel::middleware()).
    'middleware' => ['web', 'auth', 'arqel.tenant:optional'],

    'inertia' => [
        // Default Blade root view used by Inertia. `arqel:install`
        // publishes a user-owned root at `resources/views/arqel/layout.blade.php`
        // (referenced by the `arqel.layout` view name) so the framework
        // doesn't depend on Ziggy's `@routes` directive. Override here
        // when the app already has a different Inertia root, e.g.
        // pointing at the package-shipped `arqel::app` (which expects
        // Ziggy installed) or at a custom view.
        'root_view' => 'arqel.layout',

        // Vite entry points injected by the Blade root view. Override
        // when the app uses non-default paths (e.g. a separate admin
        // bundle or a Turbopack setup).
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

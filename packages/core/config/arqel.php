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
];

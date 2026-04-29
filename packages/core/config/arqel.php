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
];

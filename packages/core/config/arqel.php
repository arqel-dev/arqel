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
    ],
];

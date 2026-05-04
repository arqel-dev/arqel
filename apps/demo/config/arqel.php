<?php

declare(strict_types=1);

return [
    'panel' => [
        'default' => 'admin',
    ],
    'ai' => [
        'driver' => env('ARQEL_AI_DRIVER', 'stub'),
    ],
];

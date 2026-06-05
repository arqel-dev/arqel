<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    |
    | The showcase ships a local, offline `StubProvider` so the AI wiring
    | boots without an API key and never incurs spend. Override via
    | `ARQEL_AI_PROVIDER` to point at a real provider (claude/openai/ollama)
    | once credentials are configured.
    */
    'default_provider' => env('ARQEL_AI_PROVIDER', 'stub'),

    'providers' => [
        // Deterministic, $0, no-network provider. The package's AiServiceProvider
        // resolves each entry's `driver` FQCN from the container; StubProvider
        // takes no constructor args.
        'stub' => [
            'driver' => App\Ai\StubProvider::class,
        ],
    ],

    'cost_tracking' => [
        'enabled' => true,
        'daily_limit_usd' => (float) env('ARQEL_AI_DAILY_LIMIT', 10.0),
        'per_user_limit_usd' => (float) env('ARQEL_AI_USER_LIMIT', 1.0),
    ],

    'caching' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
];

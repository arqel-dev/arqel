<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default provider
    |--------------------------------------------------------------------------
    |
    | Which provider key from the `providers` map below `AiManager::driver()`
    | resolves when called without an explicit name. Override per-call when
    | the user-facing field needs a different model.
    */
    'default_provider' => env('ARQEL_AI_PROVIDER', 'claude'),

    'providers' => [
        // Concrete provider classes ship in AI-003 (Claude), AI-004 (OpenAI),
        // AI-005 (Ollama). Driver FQCNs are kept as strings to avoid eager
        // class resolution before the providers exist.
        'claude' => [
            'driver' => 'Arqel\\Ai\\Providers\\ClaudeProvider',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ARQEL_AI_CLAUDE_MODEL', 'claude-opus-4-7'),
            'max_tokens' => 4096,
        ],
        'openai' => [
            'driver' => 'Arqel\\Ai\\Providers\\OpenAiProvider',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('ARQEL_AI_OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => 4096,
        ],
        'ollama' => [
            'driver' => 'Arqel\\Ai\\Providers\\OllamaProvider',
            'base_url' => env('ARQEL_AI_OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('ARQEL_AI_OLLAMA_MODEL', 'llama3.1'),
            'embedding_model' => env('ARQEL_AI_OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
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

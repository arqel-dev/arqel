<?php

declare(strict_types=1);

it('boots without errors and exposes the package config', function (): void {
    expect(config('arqel-ai'))->toBeArray()
        ->and(config('arqel-ai.default_provider'))->toBe('claude')
        ->and(config('arqel-ai.providers.claude.driver'))
        ->toBe('Arqel\\Ai\\Providers\\ClaudeProvider')
        ->and(config('arqel-ai.providers.openai.driver'))
        ->toBe('Arqel\\Ai\\Providers\\OpenAiProvider')
        ->and(config('arqel-ai.providers.ollama.driver'))
        ->toBe('Arqel\\Ai\\Providers\\OllamaProvider');
});

it('publishes the config file under the expected tag', function (): void {
    $exitCode = Illuminate\Support\Facades\Artisan::call('vendor:publish', [
        '--tag' => 'arqel-ai-config',
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0)
        ->and(config_path('arqel-ai.php'))->toBeString();
});

it('exposes cost-tracking and caching defaults', function (): void {
    expect(config('arqel-ai.cost_tracking.enabled'))->toBeTrue()
        ->and(config('arqel-ai.cost_tracking.daily_limit_usd'))->toBe(10.0)
        ->and(config('arqel-ai.cost_tracking.per_user_limit_usd'))->toBe(1.0)
        ->and(config('arqel-ai.caching.enabled'))->toBeTrue()
        ->and(config('arqel-ai.caching.ttl'))->toBe(3600);
});

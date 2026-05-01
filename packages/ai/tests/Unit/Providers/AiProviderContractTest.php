<?php

declare(strict_types=1);

use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Providers\ClaudeProvider;
use Arqel\Ai\Providers\OllamaProvider;
use Arqel\Ai\Providers\OpenAiProvider;

it('AiProvider contract declares supportsVision()', function (): void {
    $reflection = new ReflectionClass(AiProvider::class);
    expect($reflection->hasMethod('supportsVision'))->toBeTrue();

    $method = $reflection->getMethod('supportsVision');
    expect((string) $method->getReturnType())->toBe('bool');
});

it('ClaudeProvider supportsVision() returns true', function (): void {
    $provider = new ClaudeProvider(apiKey: 'sk-test');
    expect($provider->supportsVision())->toBeTrue();
});

it('OpenAiProvider supportsVision() returns true', function (): void {
    $provider = new OpenAiProvider(apiKey: 'sk-test');
    expect($provider->supportsVision())->toBeTrue();
});

it('OllamaProvider supportsVision() returns true', function (): void {
    $provider = new OllamaProvider;
    expect($provider->supportsVision())->toBeTrue();
});

it('all bundled providers honour the AiProvider contract for vision', function (): void {
    $providers = [
        new ClaudeProvider(apiKey: 'sk-test'),
        new OpenAiProvider(apiKey: 'sk-test'),
        new OllamaProvider,
    ];

    foreach ($providers as $provider) {
        expect($provider)->toBeInstanceOf(AiProvider::class);
        expect($provider->supportsVision())->toBeTrue();
    }
});

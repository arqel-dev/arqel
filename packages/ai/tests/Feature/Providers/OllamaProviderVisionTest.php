<?php

declare(strict_types=1);

use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Providers\OllamaProvider;

it('chat() throws AiException when any vision option is provided', function (): void {
    $provider = new OllamaProvider;

    expect(fn () => $provider->chat(
        [['role' => 'user', 'content' => 'describe']],
        ['imageUrl' => 'https://x.test/y.jpg'],
    ))->toThrow(AiException::class, 'Ollama vision');
});

it('chat() throws AiException for the legacy `image` option as well', function (): void {
    $provider = new OllamaProvider;

    expect(fn () => $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        ['image' => 'https://x.test/y.png'],
    ))->toThrow(AiException::class, 'separate SDK');
});

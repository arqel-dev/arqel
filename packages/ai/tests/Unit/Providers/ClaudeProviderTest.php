<?php

declare(strict_types=1);

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Providers\ClaudeProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * @return array<string, mixed>
 */
function fakeClaudeResponse(string $text = 'Hello there.', int $input = 10, int $output = 4, string $model = 'claude-opus-4-7'): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => $model,
        'content' => [
            ['type' => 'text', 'text' => $text],
        ],
        'stop_reason' => 'end_turn',
        'usage' => [
            'input_tokens' => $input,
            'output_tokens' => $output,
        ],
    ];
}

it('completes a prompt and returns parsed result with cost', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeResponse('Olá mundo.', 100, 50)),
    ]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $result = $provider->complete('Diga olá');

    expect($result)->toBeInstanceOf(AiCompletionResult::class)
        ->and($result->text)->toBe('Olá mundo.')
        ->and($result->inputTokens)->toBe(100)
        ->and($result->outputTokens)->toBe(50)
        ->and($result->model)->toBe('claude-opus-4-7')
        // 100 * 15/1e6 + 50 * 75/1e6 = 0.0015 + 0.00375 = 0.00525
        ->and($result->estimatedCost)->toBe(0.00525);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'sk-test')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && data_get($body, 'model') === 'claude-opus-4-7'
            && data_get($body, 'max_tokens') === 4096
            && data_get($body, 'messages.0.role') === 'user'
            && data_get($body, 'messages.0.content') === 'Diga olá';
    });
});

it('chat() forwards system prompt and temperature options', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeResponse()),
    ]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $provider->chat(
        messages: [['role' => 'user', 'content' => 'Hi']],
        options: ['system' => 'You are concise.', 'temperature' => 0.2],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return ($body['system'] ?? null) === 'You are concise.'
            && ($body['temperature'] ?? null) === 0.2;
    });
});

it('omits system field when not provided', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeResponse()),
    ]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $provider->complete('hi');

    Http::assertSent(function (Request $request) {
        return ! array_key_exists('system', $request->data());
    });
});

it('throws AiException on failed HTTP response with status code', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'type' => 'error',
            'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key'],
        ], 401),
    ]);

    $provider = new ClaudeProvider(apiKey: 'sk-bad');

    expect(fn () => $provider->complete('hi'))
        ->toThrow(AiException::class, 'Claude API error (401)');
});

it('embed() throws AiException because Claude has no native embeddings', function (): void {
    $provider = new ClaudeProvider(apiKey: 'sk-test');

    expect(fn () => $provider->embed('text'))
        ->toThrow(AiException::class, 'Claude does not offer embeddings');
});

it('stream() throws AiException flagged as TODO follow-up', function (): void {
    $provider = new ClaudeProvider(apiKey: 'sk-test');

    expect(fn () => $provider->stream('hi', fn () => null))
        ->toThrow(AiException::class, 'AI-003-stream');
});

it('reports name and capability flags', function (): void {
    $provider = new ClaudeProvider(apiKey: 'sk-test');

    expect($provider->name())->toBe('claude')
        ->and($provider->supportsEmbeddings())->toBeFalse()
        ->and($provider->supportsStreaming())->toBeTrue();
});

it('concatenates multiple text content blocks', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-4-7',
            'content' => [
                ['type' => 'text', 'text' => 'Part one. '],
                ['type' => 'tool_use', 'id' => 't1', 'name' => 'x', 'input' => []],
                ['type' => 'text', 'text' => 'Part two.'],
            ],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1, 'output_tokens' => 2],
        ]),
    ]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $result = $provider->complete('hi');

    expect($result->text)->toBe('Part one. Part two.');
});

it('respects model and max_tokens options at call site', function (): void {
    Http::fake([
        'api.anthropic.com/*' => Http::response(fakeClaudeResponse(model: 'claude-haiku-4')),
    ]);

    $provider = new ClaudeProvider(apiKey: 'sk-test', model: 'claude-opus-4-7', maxTokens: 4096);
    $provider->complete('hi', ['model' => 'claude-haiku-4', 'max_tokens' => 256]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $body['model'] === 'claude-haiku-4' && $body['max_tokens'] === 256;
    });
});

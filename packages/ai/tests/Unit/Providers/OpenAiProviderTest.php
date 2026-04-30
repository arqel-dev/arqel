<?php

declare(strict_types=1);

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Providers\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * @return array<string, mixed>
 */
function fakeOpenAiChatResponse(string $text = 'Hello there.', int $input = 10, int $output = 4, string $model = 'gpt-4o-mini'): array
{
    return [
        'id' => 'chatcmpl-test',
        'object' => 'chat.completion',
        'model' => $model,
        'choices' => [
            [
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => $text],
                'finish_reason' => 'stop',
            ],
        ],
        'usage' => [
            'prompt_tokens' => $input,
            'completion_tokens' => $output,
            'total_tokens' => $input + $output,
        ],
    ];
}

/**
 * @param array<int, float> $vector
 *
 * @return array<string, mixed>
 */
function fakeOpenAiEmbeddingResponse(array $vector): array
{
    return [
        'object' => 'list',
        'model' => 'text-embedding-3-small',
        'data' => [
            ['object' => 'embedding', 'index' => 0, 'embedding' => $vector],
        ],
        'usage' => ['prompt_tokens' => 5, 'total_tokens' => 5],
    ];
}

it('completes a prompt and returns parsed result with cost', function (): void {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(fakeOpenAiChatResponse('Olá mundo.', 1000, 500)),
    ]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $result = $provider->complete('Diga olá');

    expect($result)->toBeInstanceOf(AiCompletionResult::class)
        ->and($result->text)->toBe('Olá mundo.')
        ->and($result->inputTokens)->toBe(1000)
        ->and($result->outputTokens)->toBe(500)
        ->and($result->model)->toBe('gpt-4o-mini')
        // 1000 * 0.15/1e6 + 500 * 0.60/1e6 = 0.00015 + 0.0003 = 0.00045
        ->and($result->estimatedCost)->toBe(0.00045);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && data_get($body, 'model') === 'gpt-4o-mini'
            && data_get($body, 'max_tokens') === 4096
            && data_get($body, 'messages.0.role') === 'user'
            && data_get($body, 'messages.0.content') === 'Diga olá';
    });
});

it('chat() prepends system message when system option provided', function (): void {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(fakeOpenAiChatResponse()),
    ]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $provider->chat(
        messages: [['role' => 'user', 'content' => 'Hi']],
        options: ['system' => 'You are concise.', 'temperature' => 0.2],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return data_get($body, 'messages.0.role') === 'system'
            && data_get($body, 'messages.0.content') === 'You are concise.'
            && data_get($body, 'messages.1.role') === 'user'
            && data_get($body, 'messages.1.content') === 'Hi'
            && ($body['temperature'] ?? null) === 0.2;
    });
});

it('chat() enables JSON mode via response_format when json_mode option set', function (): void {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(fakeOpenAiChatResponse('{"ok":true}')),
    ]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $provider->complete('reply json', ['json_mode' => true]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return data_get($body, 'response_format.type') === 'json_object';
    });
});

it('embed() returns float vector extracted from data[0].embedding', function (): void {
    $vector = array_map(fn (int $i): float => ($i + 1) / 1536, range(0, 1535));

    Http::fake([
        'api.openai.com/v1/embeddings' => Http::response(fakeOpenAiEmbeddingResponse($vector)),
    ]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $result = $provider->embed('hello world');

    expect($result)->toBeArray()
        ->and(count($result))->toBe(1536)
        ->and($result[0])->toBeFloat();

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://api.openai.com/v1/embeddings'
            && $request->hasHeader('Authorization', 'Bearer sk-test')
            && data_get($body, 'model') === 'text-embedding-3-small'
            && data_get($body, 'input') === 'hello world';
    });
});

it('throws AiException on HTTP 401 with status code', function (): void {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response([
            'error' => ['message' => 'Invalid API key', 'type' => 'invalid_request_error'],
        ], 401),
    ]);

    $provider = new OpenAiProvider(apiKey: 'sk-bad');

    expect(fn () => $provider->complete('hi'))
        ->toThrow(AiException::class, 'OpenAI API error (401)');
});

it('stream() throws AiException flagged as TODO follow-up', function (): void {
    $provider = new OpenAiProvider(apiKey: 'sk-test');

    expect(fn () => $provider->stream('hi', fn () => null))
        ->toThrow(AiException::class, 'AI-004-stream');
});

it('reports name and capability flags', function (): void {
    $provider = new OpenAiProvider(apiKey: 'sk-test');

    expect($provider->name())->toBe('openai')
        ->and($provider->supportsEmbeddings())->toBeTrue()
        ->and($provider->supportsStreaming())->toBeTrue();
});

it('respects model and max_tokens options at call site', function (): void {
    Http::fake([
        'api.openai.com/v1/chat/completions' => Http::response(fakeOpenAiChatResponse(model: 'gpt-4o')),
    ]);

    $provider = new OpenAiProvider(apiKey: 'sk-test', model: 'gpt-4o-mini', maxTokens: 4096);
    $provider->complete('hi', ['model' => 'gpt-4o', 'max_tokens' => 256]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $body['model'] === 'gpt-4o' && $body['max_tokens'] === 256;
    });
});

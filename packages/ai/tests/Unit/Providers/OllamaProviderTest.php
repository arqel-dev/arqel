<?php

declare(strict_types=1);

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Exceptions\AiException;
use Arqel\Ai\Providers\OllamaProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * @return array<string, mixed>
 */
function fakeOllamaGenerateResponse(string $text = 'Hello there.', int $input = 12, int $output = 7, string $model = 'llama3.1'): array
{
    return [
        'model' => $model,
        'created_at' => '2026-04-30T00:00:00Z',
        'response' => $text,
        'done' => true,
        'prompt_eval_count' => $input,
        'eval_count' => $output,
    ];
}

/**
 * @return array<string, mixed>
 */
function fakeOllamaChatResponse(string $text = 'Hi back.', int $input = 8, int $output = 3, string $model = 'llama3.1'): array
{
    return [
        'model' => $model,
        'created_at' => '2026-04-30T00:00:00Z',
        'message' => ['role' => 'assistant', 'content' => $text],
        'done' => true,
        'prompt_eval_count' => $input,
        'eval_count' => $output,
    ];
}

/**
 * @param array<int, float> $vector
 *
 * @return array<string, mixed>
 */
function fakeOllamaEmbeddingResponse(array $vector): array
{
    return ['embedding' => $vector];
}

it('completes a prompt and returns parsed result with zero cost', function (): void {
    Http::fake([
        'localhost:11434/api/generate' => Http::response(fakeOllamaGenerateResponse('Olá mundo.', 100, 50)),
    ]);

    $provider = new OllamaProvider;
    $result = $provider->complete('Diga olá');

    expect($result)->toBeInstanceOf(AiCompletionResult::class)
        ->and($result->text)->toBe('Olá mundo.')
        ->and($result->inputTokens)->toBe(100)
        ->and($result->outputTokens)->toBe(50)
        ->and($result->model)->toBe('llama3.1')
        ->and($result->estimatedCost)->toBe(0.0);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'http://localhost:11434/api/generate'
            && data_get($body, 'model') === 'llama3.1'
            && data_get($body, 'prompt') === 'Diga olá'
            && data_get($body, 'stream') === false;
    });
});

it('chat() extracts message.content and reports zero cost', function (): void {
    Http::fake([
        'localhost:11434/api/chat' => Http::response(fakeOllamaChatResponse('How can I help?', 20, 5)),
    ]);

    $provider = new OllamaProvider;
    $result = $provider->chat([['role' => 'user', 'content' => 'Hi']]);

    expect($result->text)->toBe('How can I help?')
        ->and($result->inputTokens)->toBe(20)
        ->and($result->outputTokens)->toBe(5)
        ->and($result->estimatedCost)->toBe(0.0);
});

it('chat() prepends system message when system option provided', function (): void {
    Http::fake([
        'localhost:11434/api/chat' => Http::response(fakeOllamaChatResponse()),
    ]);

    $provider = new OllamaProvider;
    $provider->chat(
        messages: [['role' => 'user', 'content' => 'Hi']],
        options: ['system' => 'You are concise.'],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'http://localhost:11434/api/chat'
            && data_get($body, 'messages.0.role') === 'system'
            && data_get($body, 'messages.0.content') === 'You are concise.'
            && data_get($body, 'messages.1.role') === 'user'
            && data_get($body, 'messages.1.content') === 'Hi'
            && data_get($body, 'stream') === false;
    });
});

it('embed() returns float vector extracted from data.embedding', function (): void {
    $vector = array_map(fn (int $i): float => ($i + 1) / 768, range(0, 767));

    Http::fake([
        'localhost:11434/api/embeddings' => Http::response(fakeOllamaEmbeddingResponse($vector)),
    ]);

    $provider = new OllamaProvider;
    $result = $provider->embed('hello world');

    expect($result)->toBeArray()
        ->and(count($result))->toBe(768)
        ->and($result[0])->toBeFloat();

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'http://localhost:11434/api/embeddings'
            && data_get($body, 'model') === 'nomic-embed-text'
            && data_get($body, 'prompt') === 'hello world';
    });
});

it('throws AiException on HTTP failure with status code and body', function (): void {
    Http::fake([
        'localhost:11434/api/generate' => Http::response(['error' => 'model not found'], 404),
    ]);

    $provider = new OllamaProvider;

    expect(fn () => $provider->complete('hi'))
        ->toThrow(AiException::class, 'Ollama API error (404)');
});

it('stream() throws AiException flagged as TODO follow-up', function (): void {
    $provider = new OllamaProvider;

    expect(fn () => $provider->stream('hi', fn () => null))
        ->toThrow(AiException::class, 'AI-005-stream');
});

it('reports name and capability flags', function (): void {
    $provider = new OllamaProvider;

    expect($provider->name())->toBe('ollama')
        ->and($provider->supportsEmbeddings())->toBeTrue()
        ->and($provider->supportsStreaming())->toBeTrue();
});

it('cost is exactly zero regardless of token counts', function (): void {
    Http::fake([
        'localhost:11434/api/generate' => Http::response(fakeOllamaGenerateResponse('x', 999_999, 999_999)),
        'localhost:11434/api/chat' => Http::response(fakeOllamaChatResponse('y', 12_345, 6_789)),
    ]);

    $provider = new OllamaProvider;

    expect($provider->complete('big')->estimatedCost)->toBe(0.0)
        ->and($provider->chat([['role' => 'user', 'content' => 'big']])->estimatedCost)->toBe(0.0);
});

it('respects custom base URL, model, and embedding model from constructor', function (): void {
    Http::fake([
        'ollama.internal:9999/api/generate' => Http::response(fakeOllamaGenerateResponse(model: 'mistral')),
        'ollama.internal:9999/api/embeddings' => Http::response(fakeOllamaEmbeddingResponse([0.1, 0.2])),
    ]);

    $provider = new OllamaProvider(
        baseUrl: 'http://ollama.internal:9999',
        model: 'mistral',
        embeddingModel: 'mxbai-embed-large',
    );

    $provider->complete('hi');
    $provider->embed('hi');

    Http::assertSent(function (Request $request) {
        if ($request->url() === 'http://ollama.internal:9999/api/generate') {
            return $request->data()['model'] === 'mistral';
        }

        if ($request->url() === 'http://ollama.internal:9999/api/embeddings') {
            return $request->data()['model'] === 'mxbai-embed-large';
        }

        return false;
    });
});

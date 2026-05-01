<?php

declare(strict_types=1);

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
function fakeOpenAiVisionResponse(string $text = 'A cat.', string $model = 'gpt-4o-mini'): array
{
    return [
        'id' => 'chatcmpl_test',
        'object' => 'chat.completion',
        'model' => $model,
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $text],
            'finish_reason' => 'stop',
        ]],
        'usage' => ['prompt_tokens' => 1100, 'completion_tokens' => 5, 'total_tokens' => 1105],
    ];
}

it('chat() with imageUrl emits multimodal content array', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(fakeOpenAiVisionResponse())]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $provider->chat(
        [['role' => 'user', 'content' => 'Describe this.']],
        ['imageUrl' => 'https://example.com/cat.jpg'],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return data_get($body, 'messages.0.content.0.type') === 'text'
            && data_get($body, 'messages.0.content.0.text') === 'Describe this.'
            && data_get($body, 'messages.0.content.1.type') === 'image_url'
            && data_get($body, 'messages.0.content.1.image_url.url') === 'https://example.com/cat.jpg';
    });
});

it('chat() with imageBase64 wraps raw base64 into a data URI', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(fakeOpenAiVisionResponse())]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        ['imageBase64' => 'QUFBQUE='],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $url = data_get($body, 'messages.0.content.1.image_url.url');

        return is_string($url) && str_starts_with($url, 'data:image/png;base64,QUFBQUE=');
    });
});

it('chat() preserves an already-formatted data URI from imageBase64', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(fakeOpenAiVisionResponse())]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        ['imageBase64' => 'data:image/jpeg;base64,XYZ'],
    );

    Http::assertSent(fn (Request $request) => data_get($request->data(), 'messages.0.content.1.image_url.url') === 'data:image/jpeg;base64,XYZ');
});

it('chat() without image options keeps legacy string-content body', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(fakeOpenAiVisionResponse())]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $provider->chat([['role' => 'user', 'content' => 'plain']]);

    Http::assertSent(fn (Request $request) => data_get($request->data(), 'messages.0.content') === 'plain');
});

it('chat() throws AiException when model is non-vision (gpt-3.5-turbo)', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(fakeOpenAiVisionResponse())]);

    $provider = new OpenAiProvider(apiKey: 'sk-test', model: 'gpt-3.5-turbo');

    expect(fn () => $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        ['imageUrl' => 'https://x.test/y.jpg'],
    ))->toThrow(AiException::class, 'gpt-3.5-turbo does not support vision');
});

it('chat() accepts the generic image option as URL', function (): void {
    Http::fake(['api.openai.com/*' => Http::response(fakeOpenAiVisionResponse())]);

    $provider = new OpenAiProvider(apiKey: 'sk-test');
    $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        ['image' => 'https://x.test/y.png'],
    );

    Http::assertSent(fn (Request $request) => data_get($request->data(), 'messages.0.content.1.image_url.url') === 'https://x.test/y.png');
});

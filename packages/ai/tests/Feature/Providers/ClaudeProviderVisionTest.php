<?php

declare(strict_types=1);

use Arqel\Ai\Providers\ClaudeProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

/**
 * @return array<string, mixed>
 */
function fakeClaudeVisionResponse(string $text = 'A cat.'): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-4-7',
        'content' => [['type' => 'text', 'text' => $text]],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 1100, 'output_tokens' => 5],
    ];
}

it('chat() with imageUrl injects an image block referencing the URL', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(fakeClaudeVisionResponse())]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $provider->chat(
        [['role' => 'user', 'content' => 'Describe this.']],
        ['imageUrl' => 'https://example.com/cat.jpg'],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return data_get($body, 'messages.0.content.0.type') === 'text'
            && data_get($body, 'messages.0.content.0.text') === 'Describe this.'
            && data_get($body, 'messages.0.content.1.type') === 'image'
            && data_get($body, 'messages.0.content.1.source.type') === 'url'
            && data_get($body, 'messages.0.content.1.source.url') === 'https://example.com/cat.jpg';
    });
});

it('chat() with imageBase64 emits a base64 source block with media_type', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(fakeClaudeVisionResponse())]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $provider->chat(
        [['role' => 'user', 'content' => 'Describe.']],
        ['imageBase64' => 'data:image/png;base64,QUFBQUE='],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return data_get($body, 'messages.0.content.1.source.type') === 'base64'
            && data_get($body, 'messages.0.content.1.source.media_type') === 'image/png'
            && data_get($body, 'messages.0.content.1.source.data') === 'QUFBQUE=';
    });
});

it('chat() without image options keeps legacy plain content body', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(fakeClaudeVisionResponse())]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $provider->chat([['role' => 'user', 'content' => 'plain']]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return data_get($body, 'messages.0.content') === 'plain'
            && ! $request->hasHeader('anthropic-beta');
    });
});

it('adds anthropic-beta header when vision is used', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(fakeClaudeVisionResponse())]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        ['imageUrl' => 'https://x.test/y.jpg'],
    );

    Http::assertSent(fn (Request $request) => $request->hasHeader('anthropic-beta', 'vision-2024-04-20'));
});

it('chat() supports the generic image option as URL fallback', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(fakeClaudeVisionResponse())]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        ['image' => 'https://example.com/raw.png'],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return data_get($body, 'messages.0.content.1.source.type') === 'url'
            && data_get($body, 'messages.0.content.1.source.url') === 'https://example.com/raw.png';
    });
});

it('chat() injects vision block onto the last user message even with prior turns', function (): void {
    Http::fake(['api.anthropic.com/*' => Http::response(fakeClaudeVisionResponse())]);

    $provider = new ClaudeProvider(apiKey: 'sk-test');
    $provider->chat([
        ['role' => 'user', 'content' => 'first'],
        ['role' => 'assistant', 'content' => 'ok'],
        ['role' => 'user', 'content' => 'second'],
    ], ['imageUrl' => 'https://x.test/img.png']);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return data_get($body, 'messages.0.content') === 'first'
            && data_get($body, 'messages.2.content.0.text') === 'second'
            && data_get($body, 'messages.2.content.1.type') === 'image';
    });
});

<?php

declare(strict_types=1);

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
function fakeOllamaVisionChatResponse(string $text = 'A cat.', string $model = 'llava'): array
{
    return [
        'model' => $model,
        'message' => ['role' => 'assistant', 'content' => $text],
        'done' => true,
        'prompt_eval_count' => 12,
        'eval_count' => 4,
    ];
}

it('chat() with imageBase64 posts to /api/chat with pure base64 in messages.images', function (): void {
    Http::fake([
        '*/api/chat' => Http::response(fakeOllamaVisionChatResponse()),
    ]);

    $provider = new OllamaProvider;
    $provider->chat(
        [['role' => 'user', 'content' => 'What is in this image?']],
        ['imageBase64' => 'data:image/png;base64,QUFBQUE='],
    );

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return str_ends_with($request->url(), '/api/chat')
            && data_get($body, 'messages.0.images.0') === 'QUFBQUE='
            && data_get($body, 'messages.0.content') === 'What is in this image?';
    });
});

it('chat() with imageUrl downloads the URL and sends pure base64', function (): void {
    Http::fake([
        'images.test/cat.jpg' => Http::response('binary-bytes-here', 200),
        '*/api/chat' => Http::response(fakeOllamaVisionChatResponse()),
    ]);

    $provider = new OllamaProvider;
    $provider->chat(
        [['role' => 'user', 'content' => 'describe']],
        ['imageUrl' => 'https://images.test/cat.jpg'],
    );

    $expected = base64_encode('binary-bytes-here');

    Http::assertSent(function (Request $request) use ($expected) {
        if (! str_ends_with($request->url(), '/api/chat')) {
            return false;
        }
        $body = $request->data();

        return data_get($body, 'messages.0.images.0') === $expected;
    });
});

it('chat() without vision option keeps legacy /api/chat call without images key', function (): void {
    Http::fake([
        '*/api/chat' => Http::response(fakeOllamaVisionChatResponse('hi', 'llama3.1')),
    ]);

    $provider = new OllamaProvider;
    $provider->chat([['role' => 'user', 'content' => 'hello']]);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return str_ends_with($request->url(), '/api/chat')
            && data_get($body, 'messages.0.images') === null
            && data_get($body, 'model') === 'llama3.1';
    });
});

it('chat() honours explicit model override even when vision is used', function (): void {
    Http::fake([
        '*/api/chat' => Http::response(fakeOllamaVisionChatResponse('ok', 'llava:latest')),
    ]);

    $provider = new OllamaProvider;
    $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        [
            'imageBase64' => 'AAA=',
            'model' => 'llava:latest',
        ],
    );

    Http::assertSent(function (Request $request) {
        return data_get($request->data(), 'model') === 'llava:latest';
    });
});

it('chat() falls back to default visionModel when image is provided but no model override', function (): void {
    Http::fake([
        '*/api/chat' => Http::response(fakeOllamaVisionChatResponse()),
    ]);

    // text model `llama3.1` is non-vision; visionModel should kick in.
    $provider = new OllamaProvider(model: 'llama3.1', visionModel: 'llava');
    $provider->chat(
        [['role' => 'user', 'content' => 'q']],
        ['imageBase64' => 'AAA='],
    );

    Http::assertSent(fn (Request $request) => data_get($request->data(), 'model') === 'llava');
});

it('chat() throws AiException when imageUrl download fails', function (): void {
    Http::fake([
        'images.test/missing.jpg' => Http::response('', 404),
        '*/api/chat' => Http::response(fakeOllamaVisionChatResponse()),
    ]);

    $provider = new OllamaProvider;

    expect(fn () => $provider->chat(
        [['role' => 'user', 'content' => 'describe']],
        ['imageUrl' => 'https://images.test/missing.jpg'],
    ))->toThrow(AiException::class, 'failed to download image');
});

it('supportsVision() returns true', function (): void {
    expect((new OllamaProvider)->supportsVision())->toBeTrue();
});

it('complete() with image option upgrades to /api/chat call (vision mode)', function (): void {
    Http::fake([
        '*/api/chat' => Http::response(fakeOllamaVisionChatResponse('caption text')),
        '*/api/generate' => Http::response(['response' => 'should-not-be-called']),
    ]);

    $provider = new OllamaProvider;
    $result = $provider->complete('Caption this', ['imageBase64' => 'AAA=']);

    expect($result->text)->toBe('caption text');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/api/chat'));
    Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/api/generate'));
});

<?php

declare(strict_types=1);

namespace Arqel\Ai\Providers;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic Messages API provider.
 *
 * Uses the bundled Laravel HTTP client (Guzzle under the hood) instead of an
 * Anthropic-specific SDK so the package keeps its dependency surface minimal.
 * Pricing constants reflect Claude Opus 4.7 list pricing as of April 2026 —
 * revisit on launch via web search per AI-003 implementation notes.
 */
final class ClaudeProvider implements AiProvider
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private const PRICING_INPUT_PER_MTOK = 15.0;

    private const PRICING_OUTPUT_PER_MTOK = 75.0;

    private const VISION_BETA_HEADER = 'vision-2024-04-20';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-4-7',
        private readonly int $maxTokens = 4096,
    ) {}

    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);
    }

    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        $visionPayload = $this->extractVisionPayload($options);

        if ($visionPayload !== null) {
            $messages = $this->injectVisionBlock($messages, $visionPayload);
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'messages' => $messages,
        ];

        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ];

        if ($visionPayload !== null) {
            $headers['anthropic-beta'] = self::VISION_BETA_HEADER;
        }

        $response = Http::withHeaders($headers)->post(self::API_ENDPOINT, $payload);

        if ($response->failed()) {
            throw new AiException(
                'Claude API error ('.$response->status().'): '.$response->body(),
                $response->status(),
            );
        }

        /** @var array{content: array<int, array{type: string, text?: string}>, usage: array{input_tokens: int, output_tokens: int}, model: string} $data */
        $data = $response->json();

        $text = '';
        foreach ($data['content'] as $block) {
            if (($block['type'] ?? null) === 'text' && isset($block['text'])) {
                $text .= $block['text'];
            }
        }

        $inputTokens = $data['usage']['input_tokens'];
        $outputTokens = $data['usage']['output_tokens'];

        return new AiCompletionResult(
            text: $text,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            estimatedCost: $this->estimateCost($inputTokens, $outputTokens),
            model: $data['model'],
            raw: $data,
        );
    }

    public function embed(string $text): array
    {
        throw new AiException('Claude does not offer embeddings natively. Use Voyage AI or OpenAI instead.');
    }

    public function stream(string $prompt, callable $onChunk, array $options = []): void
    {
        // SSE streaming will be implemented in AI-003-stream follow-up.
        throw new AiException('Claude streaming will be implemented in AI-003-stream follow-up.');
    }

    public function name(): string
    {
        return 'claude';
    }

    public function supportsEmbeddings(): bool
    {
        return false;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * Extract vision payload from options (supports `image`, `imageUrl`, `imageBase64`).
     *
     * Returns `['type' => 'url', 'url' => ...]` ou `['type' => 'base64',
     * 'media_type' => ..., 'data' => ...]`. `null` quando nenhuma image option.
     *
     * @param array<string, mixed> $options
     *
     * @return array{type: string, url?: string, media_type?: string, data?: string}|null
     */
    private function extractVisionPayload(array $options): ?array
    {
        $url = $options['imageUrl'] ?? null;
        $base64 = $options['imageBase64'] ?? null;
        $generic = $options['image'] ?? null;

        if (! is_string($url) && ! is_string($base64) && is_string($generic)) {
            if (str_starts_with($generic, 'data:image')) {
                $base64 = $generic;
            } else {
                $url = $generic;
            }
        }

        if (is_string($url) && $url !== '') {
            return ['type' => 'url', 'url' => $url];
        }

        if (is_string($base64) && $base64 !== '') {
            $mediaType = 'image/jpeg';
            $data = $base64;
            if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.*)$/', $base64, $matches) === 1) {
                $mediaType = $matches[1];
                $data = $matches[2];
            }

            return ['type' => 'base64', 'media_type' => $mediaType, 'data' => $data];
        }

        return null;
    }

    /**
     * Convert the last `user` message to multimodal content with the image block appended.
     *
     * Claude vision counts approximately 1100 tokens per image at the base
     * input rate; ver Anthropic pricing page para detalhes atualizados.
     *
     * @param array<int, array{role: string, content: mixed}> $messages
     * @param array{type: string, url?: string, media_type?: string, data?: string} $vision
     *
     * @return array<int, array{role: string, content: mixed}>
     */
    private function injectVisionBlock(array $messages, array $vision): array
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? null) !== 'user') {
                continue;
            }

            $original = $messages[$i]['content'] ?? '';
            $textBlock = is_string($original)
                ? ['type' => 'text', 'text' => $original]
                : null;

            $imageBlock = $vision['type'] === 'url'
                ? ['type' => 'image', 'source' => ['type' => 'url', 'url' => $vision['url'] ?? '']]
                : ['type' => 'image', 'source' => [
                    'type' => 'base64',
                    'media_type' => $vision['media_type'] ?? 'image/jpeg',
                    'data' => $vision['data'] ?? '',
                ]];

            if ($textBlock !== null) {
                $messages[$i]['content'] = [$textBlock, $imageBlock];
            } elseif (is_array($original)) {
                $original[] = $imageBlock;
                $messages[$i]['content'] = $original;
            } else {
                $messages[$i]['content'] = [$imageBlock];
            }
            break;
        }

        return $messages;
    }

    /**
     * Compute USD cost rounded to 6 decimals using Claude Opus 4.7 list pricing
     * ($15/MTok input, $75/MTok output). Vision images count ~1100 tokens at the
     * input rate — caller deve ajustar budget se necessário.
     */
    private function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $inputCost = $inputTokens * (self::PRICING_INPUT_PER_MTOK / 1_000_000);
        $outputCost = $outputTokens * (self::PRICING_OUTPUT_PER_MTOK / 1_000_000);

        return round($inputCost + $outputCost, 6);
    }
}

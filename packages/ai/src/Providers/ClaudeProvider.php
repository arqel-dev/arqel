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

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
            'content-type' => 'application/json',
        ])->post(self::API_ENDPOINT, $payload);

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
     * Compute USD cost rounded to 6 decimals using Claude Opus 4.7 list pricing
     * ($15/MTok input, $75/MTok output).
     */
    private function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $inputCost = $inputTokens * (self::PRICING_INPUT_PER_MTOK / 1_000_000);
        $outputCost = $outputTokens * (self::PRICING_OUTPUT_PER_MTOK / 1_000_000);

        return round($inputCost + $outputCost, 6);
    }
}

<?php

declare(strict_types=1);

namespace Arqel\Ai\Providers;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI Chat Completions + Embeddings API provider.
 *
 * Usa o cliente HTTP do Laravel (Guzzle por baixo) ao invés do SDK
 * `openai-php/client` para manter a superfície de dependências mínima — segue
 * o mesmo padrão de `ClaudeProvider`. Pricing default referente ao modelo
 * `gpt-4o-mini` (USD 0.15 / MTok input + USD 0.60 / MTok output em 2025).
 */
final class OpenAiProvider implements AiProvider
{
    private const CHAT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private const EMBEDDINGS_ENDPOINT = 'https://api.openai.com/v1/embeddings';

    private const EMBEDDING_MODEL_SMALL = 'text-embedding-3-small';

    private const PRICING_INPUT_PER_MTOK = 0.15;

    private const PRICING_OUTPUT_PER_MTOK = 0.60;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
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
        $payloadMessages = $messages;

        if (isset($options['system']) && is_string($options['system'])) {
            array_unshift($payloadMessages, [
                'role' => 'system',
                'content' => $options['system'],
            ]);
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'messages' => $payloadMessages,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (! empty($options['json_mode'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        } elseif (isset($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post(self::CHAT_ENDPOINT, $payload);

        if ($response->failed()) {
            throw new AiException(
                'OpenAI API error ('.$response->status().'): '.$response->body(),
                $response->status(),
            );
        }

        /** @var array{choices: array<int, array{message: array{content?: string|null}}>, usage: array{prompt_tokens: int, completion_tokens: int}, model: string} $data */
        $data = $response->json();

        $text = (string) ($data['choices'][0]['message']['content'] ?? '');
        $inputTokens = (int) ($data['usage']['prompt_tokens'] ?? 0);
        $outputTokens = (int) ($data['usage']['completion_tokens'] ?? 0);

        return new AiCompletionResult(
            text: $text,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            estimatedCost: $this->estimateCost($inputTokens, $outputTokens),
            model: $data['model'] ?? ($payload['model']),
            raw: $data,
        );
    }

    public function embed(string $text): array
    {
        // AI-004-large embeddings option — adicionar suporte a `text-embedding-3-large`
        // (3072d) via assinatura estendida ou opção do construtor.
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ])->post(self::EMBEDDINGS_ENDPOINT, [
            'model' => self::EMBEDDING_MODEL_SMALL,
            'input' => $text,
        ]);

        if ($response->failed()) {
            throw new AiException(
                'OpenAI API error ('.$response->status().'): '.$response->body(),
                $response->status(),
            );
        }

        /** @var array{data: array<int, array{embedding: array<int, float>}>} $data */
        $data = $response->json();

        /** @var array<int, float> $embedding */
        $embedding = $data['data'][0]['embedding'] ?? [];

        return $embedding;
    }

    public function stream(string $prompt, callable $onChunk, array $options = []): void
    {
        // SSE streaming será implementado em AI-004-stream follow-up.
        throw new AiException('OpenAI streaming will be implemented in AI-004-stream follow-up.');
    }

    public function name(): string
    {
        return 'openai';
    }

    public function supportsEmbeddings(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    /**
     * Calcula custo USD arredondado para 6 decimais usando pricing do gpt-4o-mini
     * (USD 0.15/MTok input, USD 0.60/MTok output em 2025).
     */
    private function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $inputCost = $inputTokens * (self::PRICING_INPUT_PER_MTOK / 1_000_000);
        $outputCost = $outputTokens * (self::PRICING_OUTPUT_PER_MTOK / 1_000_000);

        return round($inputCost + $outputCost, 6);
    }
}

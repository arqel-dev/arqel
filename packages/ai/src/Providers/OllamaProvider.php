<?php

declare(strict_types=1);

namespace Arqel\Ai\Providers;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Http;

/**
 * Ollama local LLM provider.
 *
 * Conversa com um daemon Ollama rodando localmente (default `http://localhost:11434`)
 * via cliente HTTP do Laravel. Usa `/api/generate` para `complete()`, `/api/chat`
 * (endpoint nativo desde 2024) para `chat()` e `/api/embeddings` para `embed()`.
 * Como execução é local, `estimatedCost` é sempre `0.0` — útil para dev offline,
 * CI sem chaves de API e self-hosting econômico.
 */
final class OllamaProvider implements AiProvider
{
    public function __construct(
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly string $model = 'llama3.1',
        private readonly string $embeddingModel = 'nomic-embed-text',
    ) {}

    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        if (isset($options['options']) && is_array($options['options'])) {
            $payload['options'] = $options['options'];
        }

        if (isset($options['system']) && is_string($options['system'])) {
            $payload['system'] = $options['system'];
        }

        $response = Http::post($this->endpoint('/api/generate'), $payload);

        if ($response->failed()) {
            throw new AiException(
                'Ollama API error ('.$response->status().'): '.$response->body(),
                $response->status(),
            );
        }

        /** @var array{response?: string, prompt_eval_count?: int, eval_count?: int, model?: string} $data */
        $data = $response->json();

        $inputTokens = (int) ($data['prompt_eval_count'] ?? 0);
        $outputTokens = (int) ($data['eval_count'] ?? 0);
        $model = $data['model'] ?? $payload['model'];

        return new AiCompletionResult(
            text: $data['response'] ?? '',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            estimatedCost: 0.0,
            model: is_string($model) ? $model : $this->model,
            raw: $data,
        );
    }

    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        if (
            isset($options['image'])
            || isset($options['imageUrl'])
            || isset($options['imageBase64'])
        ) {
            throw new AiException(
                'Ollama vision (llava model) requires separate SDK; consider using ClaudeProvider or OpenAiProvider for vision tasks',
            );
        }

        $payloadMessages = $messages;

        if (isset($options['system']) && is_string($options['system'])) {
            array_unshift($payloadMessages, [
                'role' => 'system',
                'content' => $options['system'],
            ]);
        }

        $payload = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $payloadMessages,
            'stream' => false,
        ];

        if (isset($options['options']) && is_array($options['options'])) {
            $payload['options'] = $options['options'];
        }

        $response = Http::post($this->endpoint('/api/chat'), $payload);

        if ($response->failed()) {
            throw new AiException(
                'Ollama API error ('.$response->status().'): '.$response->body(),
                $response->status(),
            );
        }

        /** @var array{message?: array{content?: string}, prompt_eval_count?: int, eval_count?: int, model?: string} $data */
        $data = $response->json();

        $inputTokens = (int) ($data['prompt_eval_count'] ?? 0);
        $outputTokens = (int) ($data['eval_count'] ?? 0);
        $model = $data['model'] ?? $payload['model'];

        return new AiCompletionResult(
            text: $data['message']['content'] ?? '',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            estimatedCost: 0.0,
            model: is_string($model) ? $model : $this->model,
            raw: $data,
        );
    }

    public function embed(string $text): array
    {
        $response = Http::post($this->endpoint('/api/embeddings'), [
            'model' => $this->embeddingModel,
            'prompt' => $text,
        ]);

        if ($response->failed()) {
            throw new AiException(
                'Ollama API error ('.$response->status().'): '.$response->body(),
                $response->status(),
            );
        }

        /** @var array{embedding?: array<int, float>} $data */
        $data = $response->json();

        /** @var array<int, float> $embedding */
        $embedding = $data['embedding'] ?? [];

        return $embedding;
    }

    public function stream(string $prompt, callable $onChunk, array $options = []): void
    {
        // NDJSON streaming será implementado em AI-005-stream follow-up.
        throw new AiException('Ollama streaming will be implemented in AI-005-stream follow-up.');
    }

    public function name(): string
    {
        return 'ollama';
    }

    public function supportsEmbeddings(): bool
    {
        return true;
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}

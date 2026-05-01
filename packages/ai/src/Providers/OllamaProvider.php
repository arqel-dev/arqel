<?php

declare(strict_types=1);

namespace Arqel\Ai\Providers;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Ollama local LLM provider.
 *
 * Conversa com um daemon Ollama rodando localmente (default `http://localhost:11434`)
 * via cliente HTTP do Laravel. Usa `/api/generate` para `complete()`, `/api/chat`
 * (endpoint nativo desde 2024) para `chat()` e `/api/embeddings` para `embed()`.
 * Como execução é local, `estimatedCost` é sempre `0.0` — útil para dev offline,
 * CI sem chaves de API e self-hosting econômico.
 *
 * Vision: suportada nativamente via modelos `llava`, `bakllava` ou
 * `llama3.2-vision`. Imagens viajam em `messages[].images: [base64,...]` no
 * endpoint `/api/chat` — base64 puro (sem prefixo `data:image/...;base64,`).
 */
final class OllamaProvider implements AiProvider
{
    public function __construct(
        private readonly string $baseUrl = 'http://localhost:11434',
        private readonly string $model = 'llama3.1',
        private readonly string $embeddingModel = 'nomic-embed-text',
        private readonly string $visionModel = 'llava',
    ) {}

    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        if ($this->hasImageOption($options)) {
            return $this->chat([
                ['role' => 'user', 'content' => $prompt],
            ], $options);
        }

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
        $images = $this->extractImagesFromOptions($options);

        $payloadMessages = $messages;

        if (isset($options['system']) && is_string($options['system'])) {
            array_unshift($payloadMessages, [
                'role' => 'system',
                'content' => $options['system'],
            ]);
        }

        if ($images !== []) {
            $payloadMessages = $this->attachImagesToLastUserMessage($payloadMessages, $images);
        }

        $modelOption = $options['model'] ?? null;
        $resolvedModel = is_string($modelOption) && $modelOption !== ''
            ? $modelOption
            : ($images !== [] ? $this->visionModel : $this->model);

        $payload = [
            'model' => $resolvedModel,
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

    public function supportsVision(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function hasImageOption(array $options): bool
    {
        return isset($options['image'])
            || isset($options['imageUrl'])
            || isset($options['imageBase64']);
    }

    /**
     * Extract a list of pure-base64 image strings from options.
     *
     * Aceita `imageBase64` (string ou array<string>), `imageUrl` (string ou
     * array<string> — baixa via HTTP defensive) e `image` (legacy fallback).
     * Strip do prefix `data:image/...;base64,` quando presente. URLs são
     * baixadas com timeout 5s e convertidas para base64.
     *
     * @param array<string, mixed> $options
     *
     * @return array<int, string>
     */
    private function extractImagesFromOptions(array $options): array
    {
        $images = [];

        $base64Source = $options['imageBase64'] ?? null;
        if (is_string($base64Source) && $base64Source !== '') {
            $images[] = $this->stripBase64Prefix($base64Source);
        } elseif (is_array($base64Source)) {
            foreach ($base64Source as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $images[] = $this->stripBase64Prefix($entry);
                }
            }
        }

        $urlSource = $options['imageUrl'] ?? null;
        if (is_string($urlSource) && $urlSource !== '') {
            $images[] = $this->downloadAsBase64($urlSource);
        } elseif (is_array($urlSource)) {
            foreach ($urlSource as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $images[] = $this->downloadAsBase64($entry);
                }
            }
        }

        $generic = $options['image'] ?? null;
        if ($images === [] && is_string($generic) && $generic !== '') {
            if (str_starts_with($generic, 'data:image') || ! preg_match('#^https?://#i', $generic)) {
                $images[] = $this->stripBase64Prefix($generic);
            } else {
                $images[] = $this->downloadAsBase64($generic);
            }
        } elseif ($images === [] && is_array($generic)) {
            foreach ($generic as $entry) {
                if (! is_string($entry) || $entry === '') {
                    continue;
                }
                if (str_starts_with($entry, 'data:image') || ! preg_match('#^https?://#i', $entry)) {
                    $images[] = $this->stripBase64Prefix($entry);
                } else {
                    $images[] = $this->downloadAsBase64($entry);
                }
            }
        }

        return $images;
    }

    private function stripBase64Prefix(string $value): string
    {
        if (preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,(.*)$/', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    private function downloadAsBase64(string $url): string
    {
        try {
            $response = Http::timeout(5)->get($url);
        } catch (Throwable $e) {
            throw new AiException(
                'Ollama vision: failed to download image from URL ('.$url.'): '.$e->getMessage(),
                0,
                $e,
            );
        }

        if ($response->failed()) {
            throw new AiException(
                'Ollama vision: failed to download image from URL ('.$url.') — HTTP '.$response->status(),
                $response->status(),
            );
        }

        return base64_encode($response->body());
    }

    /**
     * @param array<int, array{role: string, content: mixed, images?: array<int, string>}> $messages
     * @param array<int, string> $images
     *
     * @return array<int, array{role: string, content: mixed, images?: array<int, string>}>
     */
    private function attachImagesToLastUserMessage(array $messages, array $images): array
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['role'] ?? null) !== 'user') {
                continue;
            }

            $existing = isset($messages[$i]['images']) && is_array($messages[$i]['images'])
                ? $messages[$i]['images']
                : [];

            $messages[$i]['images'] = array_values(array_merge($existing, $images));
            break;
        }

        return $messages;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').$path;
    }
}

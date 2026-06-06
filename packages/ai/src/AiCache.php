<?php

declare(strict_types=1);

namespace Arqel\Ai;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Wrapper sobre o cache do Laravel especializado em respostas
 * `AiCompletionResult`. Chave determinística por `(prompt, options)`
 * permite que prompts idênticos curto-circuitem o provider e cortem
 * custos. Desativado quando `arqel-ai.caching.enabled` é falso —
 * `has()` retorna sempre false e `get()` retorna null nesse caso.
 */
final class AiCache
{
    private const KEY_PREFIX = 'arqel-ai:';

    /**
     * @param array<string, mixed> $options
     */
    public function has(string $prompt, array $options): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return $this->store()->has($this->key($prompt, $options));
    }

    /**
     * @param array<string, mixed> $options
     */
    public function get(string $prompt, array $options): ?AiCompletionResult
    {
        if (! $this->enabled()) {
            return null;
        }

        /** @var mixed $value */
        $value = $this->store()->get($this->key($prompt, $options));

        return is_array($value) ? $this->rehydrate($value) : null;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function put(string $prompt, array $options, AiCompletionResult $result): void
    {
        if (! $this->enabled()) {
            return;
        }

        /** @var int $ttl */
        $ttl = config('arqel-ai.caching.ttl', 3600);

        // Store a plain array snapshot rather than the result object. Laravel's
        // default database cache driver (Laravel 13, cache.serializable_classes
        // = false) unserializes any stored object into a __PHP_Incomplete_Class,
        // which would make get() miss forever and silently double-count cost
        // (issue #82). An array round-trips losslessly on every driver.
        $this->store()->put($this->key($prompt, $options), $this->snapshot($result), $ttl);
    }

    /**
     * @return array{
     *     text: string,
     *     inputTokens: int,
     *     outputTokens: int,
     *     estimatedCost: float|null,
     *     model: string|null,
     *     raw: array<string, mixed>|null,
     * }
     */
    private function snapshot(AiCompletionResult $result): array
    {
        return [
            'text' => $result->text,
            'inputTokens' => $result->inputTokens,
            'outputTokens' => $result->outputTokens,
            'estimatedCost' => $result->estimatedCost,
            'model' => $result->model,
            'raw' => $result->raw,
        ];
    }

    /**
     * Rebuild an `AiCompletionResult` from a cached array snapshot. Returns
     * null for malformed/legacy payloads so the caller treats them as a miss.
     *
     * @param array<array-key, mixed> $value
     */
    private function rehydrate(array $value): ?AiCompletionResult
    {
        if (! is_string($value['text'] ?? null)
            || ! is_int($value['inputTokens'] ?? null)
            || ! is_int($value['outputTokens'] ?? null)) {
            return null;
        }

        $estimatedCost = $value['estimatedCost'] ?? null;
        $model = $value['model'] ?? null;
        $raw = $value['raw'] ?? null;

        if (is_array($raw)) {
            /** @var array<string, mixed> $normalisedRaw */
            $normalisedRaw = [];
            foreach ($raw as $rawKey => $rawValue) {
                $normalisedRaw[(string) $rawKey] = $rawValue;
            }
            $raw = $normalisedRaw;
        } else {
            $raw = null;
        }

        return new AiCompletionResult(
            text: $value['text'],
            inputTokens: $value['inputTokens'],
            outputTokens: $value['outputTokens'],
            estimatedCost: is_float($estimatedCost) || is_int($estimatedCost) ? (float) $estimatedCost : null,
            model: is_string($model) ? $model : null,
            raw: $raw,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function key(string $prompt, array $options): string
    {
        return self::KEY_PREFIX.md5((string) json_encode([$prompt, $options]));
    }

    private function enabled(): bool
    {
        return (bool) config('arqel-ai.caching.enabled', true);
    }

    private function store(): CacheRepository
    {
        return Cache::store();
    }
}

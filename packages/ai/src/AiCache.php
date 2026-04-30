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

        $value = $this->store()->get($this->key($prompt, $options));

        return $value instanceof AiCompletionResult ? $value : null;
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

        $this->store()->put($this->key($prompt, $options), $result, $ttl);
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

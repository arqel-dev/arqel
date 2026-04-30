<?php

declare(strict_types=1);

namespace Arqel\Ai;

use Arqel\Ai\Contracts\AiProvider;
use Arqel\Ai\Events\AiCompletionGenerated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Front-door para todas as chamadas AI da app. Resolve o provider
 * por nome, aplica cache (curto-circuita prompts repetidos), enforça
 * limites de custo via `CostTracker`, persiste a chamada e dispara
 * `AiCompletionGenerated` para listeners user-land.
 *
 * `complete()` e `chat()` partilham o mesmo fluxo (cache → assert →
 * provider → record → cache.put → event). `embed()` deliberadamente
 * pula cache (vetores são leves de re-gerar e o ganho de cache hit
 * é tipicamente baixo) mas mantém o assert de limite.
 */
final class AiManager
{
    /**
     * @param  array<string, AiProvider>  $providers  pre-resolved by name
     */
    public function __construct(
        private readonly array $providers,
        private readonly ?CostTracker $costTracker = null,
        private readonly ?AiCache $cache = null,
    ) {}

    public function provider(?string $name = null): AiProvider
    {
        $name ??= $this->stringConfig('arqel-ai.default_provider');

        if ($name === null || ! isset($this->providers[$name])) {
            throw new InvalidArgumentException(
                "AI provider '".($name ?? 'null')."' is not configured"
            );
        }

        return $this->providers[$name];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        $cached = $this->cache?->get($prompt, $options);
        if ($cached !== null) {
            return $cached;
        }

        $userId = $this->currentUserId();
        $this->costTracker?->assertWithinLimit($userId);

        $provider = $this->resolveProviderFor($options);
        $result = $provider->complete($prompt, $options);

        $this->costTracker?->record($userId, $result, $provider->name());
        $this->cache?->put($prompt, $options, $result);

        Event::dispatch(new AiCompletionGenerated($result, $provider->name(), $userId));

        return $result;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): AiCompletionResult
    {
        $cacheKey = (string) json_encode($messages);

        $cached = $this->cache?->get($cacheKey, $options);
        if ($cached !== null) {
            return $cached;
        }

        $userId = $this->currentUserId();
        $this->costTracker?->assertWithinLimit($userId);

        $provider = $this->resolveProviderFor($options);
        $result = $provider->chat($messages, $options);

        $this->costTracker?->record($userId, $result, $provider->name());
        $this->cache?->put($cacheKey, $options, $result);

        Event::dispatch(new AiCompletionGenerated($result, $provider->name(), $userId));

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, float>
     */
    public function embed(string $text, array $options = []): array
    {
        $userId = $this->currentUserId();
        $this->costTracker?->assertWithinLimit($userId);

        $provider = $this->resolveProviderFor($options);

        return $provider->embed($text);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveProviderFor(array $options): AiProvider
    {
        $name = isset($options['provider']) && is_string($options['provider'])
            ? $options['provider']
            : null;

        return $this->provider($name);
    }

    private function currentUserId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    private function stringConfig(string $key): ?string
    {
        /** @var mixed $value */
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

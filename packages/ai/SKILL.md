# SKILL.md — arqel/ai

## Purpose

`arqel/ai` é o pacote AI-assist do Arqel — fornece campos como `AiTextField`, `AiTranslateField`, `AiSelectField`, `AiExtractField` e `AiImageField` que delegam a um provider (Claude / OpenAI / Ollama) atrás de um único contrato.

O pacote é provider-agnóstico por design: o consumidor escolhe `claude`, `openai` ou `ollama` em `config/arqel-ai.php` e os fields chamam `AiManager::driver()` sem saber a implementação concreta.

## Status

Entregue em AI-001 + AI-002:

- `composer.json` com `arqel/core` em `require` e SDKs nativos (`anthropic/anthropic-php`, `openai-php/client`, `ollama-laravel`) em `suggest:` — apps opt-in apenas para o provider que vão usar.
- `Arqel\Ai\AiServiceProvider` auto-discovered + publica `config/arqel-ai.php` via `vendor:publish --tag=arqel-ai-config`.
- `Arqel\Ai\Contracts\AiProvider` — métodos `complete`, `chat`, `embed`, `stream`, `name`, `supportsEmbeddings`, `supportsStreaming`.
- `Arqel\Ai\AiCompletionResult` — final readonly value-object com `text`, `inputTokens`, `outputTokens`, `estimatedCost`, `model`, `raw` + helper `totalTokens()`.
- `Arqel\Ai\Exceptions\AiException` — base RuntimeException para erros de provider (network, auth, rate-limit, content-policy).
- Suite Pest com Orchestra Testbench (3 unit + 3 feature).

Por chegar:

- **AI-003** `ClaudeProvider` (HTTP direto via `Illuminate\Support\Facades\Http` — SDK Anthropic PHP ainda em flux em Abril/2026).
- **AI-004** `OpenAiProvider` via `openai-php/client`.
- **AI-005** `OllamaProvider` para LLM local.
- **AI-006** `AiManager` singleton + rate limiting + cost tracking + caching.
- **AI-007..AI-011** os 5 field types (`AiTextField`/`AiTranslateField`/`AiSelectField`/`AiExtractField`/`AiImageField`).
- **AI-012** prompt library reutilizável.
- **AI-013** MCP tools AI-generated (cross-package com `arqel/mcp`).

## Conventions

- **Nunca invocar provider sem confirmação do user**. AI tem custo monetário — toda operação iniciada por um field deve passar pelo `confirm` do Action ou flash de "preview before commit". O cost runaway é o anti-padrão #1 deste pacote.
- **Tokens são contabilizados pelo provider, não estimados localmente**. `AiCompletionResult::$inputTokens` / `$outputTokens` vêm da API response. `estimatedCost` é null se o provider não expõe pricing.
- **Provider FQCN guardados como string** em `config/arqel-ai.php` (não `::class`) para evitar resolução eager antes dos concretes existirem.
- **`raw` carrega o payload bruto**. Streaming, tool calls e finish_reason ficam aí — não inventamos campos no value-object.
- Embedding-only ou streaming-only providers DEVEM lançar `AiException` em métodos não suportados E reportar `supportsEmbeddings()` / `supportsStreaming()` corretamente. Caller usa `supports*` para gated fluxo.

## Anti-patterns

- ❌ Hard-depender de SDKs de provider — todos em `suggest:`.
- ❌ Chamar AI em hot paths sem cache (`config('arqel-ai.caching.enabled')` é `true` por default — TTL 3600s).
- ❌ Aceitar prompt completo do user sem template/sanitização (futuro AI-012 cobre prompt injection guard).
- ❌ Esquecer rate limiting per-user — `cost_tracking.per_user_limit_usd` é hard limit, não advisory.
- ❌ Mutar `AiCompletionResult` — é `final readonly`. Faça `new AiCompletionResult(...)` se precisar de uma cópia transformada.

## Examples

### Configurar provider default

```php
// config/arqel-ai.php
return [
    'default_provider' => env('ARQEL_AI_PROVIDER', 'claude'),
    'providers' => [
        'claude' => [
            'driver' => 'Arqel\\Ai\\Providers\\ClaudeProvider',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ARQEL_AI_CLAUDE_MODEL', 'claude-opus-4-7'),
            'max_tokens' => 4096,
        ],
        // ...
    ],
];
```

### Implementar um provider customizado (future-facing)

```php
namespace App\Ai;

use Arqel\Ai\AiCompletionResult;
use Arqel\Ai\Contracts\AiProvider;

final class MyCustomProvider implements AiProvider
{
    public function complete(string $prompt, array $options = []): AiCompletionResult
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]], $options);
    }

    // ... chat / embed / stream / name / supports*
}
```

### Consumir um result

```php
$result = app(\Arqel\Ai\Contracts\AiProvider::class)
    ->complete('Resume isto em 1 frase: ...');

logger()->info('AI call', [
    'tokens' => $result->totalTokens(),
    'cost'   => $result->estimatedCost,
    'model'  => $result->model,
]);
```

## Related

- `arqel/core` — `Resource` lifecycle hooks que vão dispatch AI calls de afterCreate/afterUpdate (futuro).
- `arqel/fields` — base `Field` que `AiTextField` etc. estendem.
- `arqel/mcp` — AI-013 expõe Resource analysis como MCP tools para Claude Desktop / Claude Code.
- PLANNING/10-fase-3-avancadas.md §2 (AI fields) — roadmap completo.

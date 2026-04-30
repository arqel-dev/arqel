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

Entregue em AI-003..AI-005:

- **`Arqel\Ai\Providers\ClaudeProvider`** via `Http` facade (`api.anthropic.com/v1/messages`, header `anthropic-version`); pricing Opus 4.7 ($15/$75 MTok); `embed()`/`stream()` lançam `AiException`.
- **`Arqel\Ai\Providers\OpenAiProvider`** via `Http` (`/v1/chat/completions` + `/v1/embeddings`); `system` via `array_unshift`; JSON mode (`response_format: {type:'json_object'}`); embeddings 1536d (`text-embedding-3-small`); pricing gpt-4o-mini.
- **`Arqel\Ai\Providers\OllamaProvider`** via `Http` (`/api/generate` + `/api/chat` + `/api/embeddings`); cost SEMPRE 0.0; embedding model `nomic-embed-text` por default.

Entregue em AI-006:

- **`Arqel\Ai\AiManager`** (final) — front-door para todas as chamadas. Resolve provider por nome (`config('arqel-ai.default_provider')` ou `options.provider`), aplica cache, enforça `CostTracker::assertWithinLimit(Auth::id())`, persiste a chamada via `CostTracker::record`, dispara `AiCompletionGenerated`. `embed()` bypassa cache (vetores são leves de re-gerar).
- **`Arqel\Ai\CostTracker`** (final) — wrapper Eloquent sobre `ai_usage`. Limites configurados em `arqel-ai.cost_tracking.{daily_limit_usd, per_user_limit_usd}` — `null` ou `<= 0` é tratado como **ilimitado** (útil em dev). Lança `DailyLimitExceeded` / `UserLimitExceeded` (subclasses de `AiException`).
- **`Arqel\Ai\AiCache`** (final) — wrapper sobre `Cache::store()`. Key determinística `arqel-ai:{md5(json_encode([prompt, options]))}`. TTL de `arqel-ai.caching.ttl` (default 3600s). Desativado quando `arqel-ai.caching.enabled === false` (útil para apps que escolheram cache layer próprio).
- **`Arqel\Ai\Models\AiUsage`** (final Eloquent) — tabela `ai_usage` (`user_id`, `provider`, `model`, `input_tokens`, `output_tokens`, `cost_usd`, `prompt_hash`, timestamps + index em `created_at`).
- **`Arqel\Ai\Events\AiCompletionGenerated`** (final) — Dispatchable+SerializesModels com `result, providerName, userId`. Listeners user-land podem persistir métricas customizadas, alertar, ou invalidate cache externos.
- Migration `2026_05_01_000000_create_ai_usage_table` auto-discovered via `hasMigration('create_ai_usage_table')` no provider.
- `AiServiceProvider::packageRegistered()` instancia singleton `AiManager` resolvendo cada entry de `arqel-ai.providers` lazily via container (`$app->make($driver, $args)`); entries com `class_exists($driver) === false` são silenciosamente ignoradas.
- **27 Pest tests** novos (10 AiManager + 6 CostTracker + 3 AiCache + recovery dos existentes).

Entregue em AI-007 (PHP slice — componente React `AiTextInput.tsx` fica para batch futuro):

- **`Arqel\Ai\Fields\AiTextField`** (final, estende `Arqel\Fields\Types\TextareaField`) — geração de texto via AI a partir de prompt template. Setters fluentes `prompt(string|Closure)`, `provider(?string)`, `aiOptions(array)`, `contextFields(array)`, `maxLength(int)`, `buttonLabel(string)`. Método `generate(array $formData): string` resolve placeholders `{fieldName}`, chama `AiManager::complete()` e trunca quando excede `maxLength`. `getTypeSpecificProps()` **NUNCA** expõe o prompt template ao cliente (segurança/IP).
- **`Arqel\Ai\Http\Controllers\AiGenerateController`** (single-action) registrado em `routes/web.php` como `POST /admin/{resource}/fields/{field}/generate` (named `arqel.ai.generate`, middleware `web,auth`). Resolve o `Resource` via `ResourceRegistry::findBySlug()`, encontra o `AiTextField` pelo nome em `Resource::fields()` e devolve `{text: string}`. Authorization: Gate `use-ai` opt-in — quando não definida, allow por default.
- `arqel/fields` adicionado ao `composer.json` (path repo + `@dev`).
- 8 testes unit + 3 testes feature.

Por chegar:

- **AI-007 React** componente `AiTextInput.tsx` (botão Generate + estado loading + replace value).
- **AI-008..AI-011** os 4 field types restantes (`AiTranslateField`/`AiSelectField`/`AiExtractField`/`AiImageField`).
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

### AiTextField (AI-007)

```php
use Arqel\Ai\Fields\AiTextField;

public function fields(): array
{
    return [
        Field::text('title')->required(),

        (new AiTextField('summary'))
            ->prompt('Resume em 1 frase o post intitulado "{title}".')
            ->contextFields(['title'])
            ->provider('claude')
            ->aiOptions(['temperature' => 0.4])
            ->maxLength(280)
            ->buttonLabel('Gerar resumo'),
    ];
}
```

O placeholder `{title}` é resolvido server-side em `generate()` com o
`formData` que o cliente envia para `POST /admin/{slug}/fields/summary/generate`.
O prompt template **não trafega** para o cliente — apps com prompts que
contêm regras de negócio podem mantê-los privados.

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

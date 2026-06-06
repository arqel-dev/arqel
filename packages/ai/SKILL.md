# SKILL.md — arqel-dev/ai

## Purpose

`arqel-dev/ai` é o pacote AI-assist do Arqel — fornece campos como `AiTextField`, `AiTranslateField`, `AiSelectField`, `AiExtractField` e `AiImageField` que delegam a um provider (Claude / OpenAI / Ollama) atrás de um único contrato.

O pacote é provider-agnóstico por design: o consumidor escolhe `claude`, `openai` ou `ollama` em `config/arqel-ai.php` e os fields chamam `AiManager::driver()` sem saber a implementação concreta.

## Status

### Entregue

**AI-001 + AI-002 — Bootstrap + contratos**
- `composer.json` com `arqel-dev/core` em `require` e SDKs nativos (`anthropic/anthropic-php`, `openai-php/client`, `ollama-laravel`) em `suggest:` — apps opt-in apenas para o provider que vão usar.
- `Arqel\Ai\AiServiceProvider` auto-discovered + publica `config/arqel-ai.php` via `vendor:publish --tag=arqel-ai-config`.
- `Arqel\Ai\Contracts\AiProvider` — métodos `complete`, `chat`, `embed`, `stream`, `name`, `supportsEmbeddings`, `supportsStreaming`.
- `Arqel\Ai\AiCompletionResult` — final readonly value-object com `text`, `inputTokens`, `outputTokens`, `estimatedCost`, `model`, `raw` + helper `totalTokens()`.
- `Arqel\Ai\Exceptions\AiException` — base RuntimeException para erros de provider (network, auth, rate-limit, content-policy).

**AI-003..AI-005 — Providers**
- **`Arqel\Ai\Providers\ClaudeProvider`** via `Http` facade (`api.anthropic.com/v1/messages`, header `anthropic-version`); pricing Opus 4.7 ($15/$75 MTok); `embed()` lança `AiException` (Anthropic não tem embeddings nativos); `stream()` parseia SSE Anthropic (`event: content_block_delta` → `delta.text`).
- **`Arqel\Ai\Providers\OpenAiProvider`** via `Http` (`/v1/chat/completions` + `/v1/embeddings`); `system` injetado via `array_unshift` apenas quando `options.system` está presente; JSON mode (`response_format: {type:'json_object'}`); embeddings 1536d (`text-embedding-3-small`); pricing gpt-4o-mini ($0.15/$0.60 MTok); SSE `data: {…}` → `choices[0].delta.content`.
- **`Arqel\Ai\Providers\OllamaProvider`** via `Http` (`/api/generate` + `/api/chat` + `/api/embeddings`); cost SEMPRE 0.0 (execução local); embedding model `nomic-embed-text` por default; `eval_count`/`prompt_eval_count` ausentes caem em 0; streaming NDJSON (`{"response": "...chunk...", "done": false}\n`).

**AI-006 — Manager + Cost + Cache**
- **`Arqel\Ai\AiManager`** (final) — front-door para todas as chamadas. Resolve provider por nome (`config('arqel-ai.default_provider')` ou `options.provider`), aplica cache, enforça `CostTracker::assertWithinLimit(Auth::id())`, persiste a chamada via `CostTracker::record`, dispara `AiCompletionGenerated`. `embed()` bypassa cache (vetores são leves de re-gerar). Lança `InvalidArgumentException` quando provider não existe ou default é null.
- **`Arqel\Ai\CostTracker`** (final) — wrapper Eloquent sobre `ai_usage`. Limites em `arqel-ai.cost_tracking.{daily_limit_usd, per_user_limit_usd}` — `null` ou `<= 0` é tratado como **ilimitado** (útil em dev). Lança `DailyLimitExceeded` / `UserLimitExceeded` (subclasses de `AiException`). `getCostSince()`/`getCostForUserSince()` filtram via `Carbon::today()` por default. **Offline-safe** (issue #49): `arqel-ai.cost_tracking.enabled === false` desliga totalmente o tracker (zero acessos à BD, espelhando `AiCache`); e quando a tabela `ai_usage` ainda não foi migrada, as leituras degradam para custo `0.0` via `Schema::hasTable` em vez de lançar `QueryException`.
- **`Arqel\Ai\AiCache`** (final) — wrapper sobre `Cache::store()`. Key determinística `arqel-ai:{md5(json_encode([prompt, options]))}` — mesmo prompt + options idênticos → mesma key (regression-tested). TTL `arqel-ai.caching.ttl` (default 3600s). Desativado quando `arqel-ai.caching.enabled === false`. **Persiste um snapshot em array** do `AiCompletionResult` (não o objeto cru) e reidrata via `new AiCompletionResult(...)` no `get()` — necessário porque o driver de cache `database` default do Laravel 13 (com `cache.serializable_classes=false`) unserializa objetos para `__PHP_Incomplete_Class`, o que fazia o `get()` errar sempre e duplicar custo (issue #82). Snapshots legados/inválidos degradam para cache miss.
- **`Arqel\Ai\Models\AiUsage`** (final Eloquent) — tabela `ai_usage` (`user_id`, `provider`, `model`, `input_tokens`, `output_tokens`, `cost_usd`, `prompt_hash`, timestamps + index em `created_at`).
- **`Arqel\Ai\Events\AiCompletionGenerated`** (final) — Dispatchable+SerializesModels com `result, providerName, userId`. Listeners user-land podem persistir métricas customizadas, alertar, ou invalidar cache externos.
- Migration `2026_05_01_000000_create_ai_usage_table` auto-discovered via `hasMigration('2026_05_01_000000_create_ai_usage_table')` no provider — o nome **datado** (não `create_ai_usage_table`) tem de bater com o ficheiro shipado, senão `vendor:publish --tag=arqel-ai-migrations` não resolve (issue #49).

**AI-007..AI-011 — Fields (PHP slice)**

| Field | Action principal | Rota | React |
|---|---|---|---|
| `AiTextField` (estende `TextField`) | `generate(formData): string` — placeholders `{name}` resolvidos server-side, trunca por `maxLength` | `POST /admin/{r}/fields/{f}/generate` | entregue |
| `AiTranslateField` | `translate(text, target, ?source)`, `translateAll(translations, source)` — só preenche idiomas vazios, nunca sobrescreve traduções manuais | `POST .../translate` | follow-up |
| `AiSelectField` | `classify(formData): ?string` — normaliza output (trim/lower/strip) e valida contra `options` de forma case-insensitive (retorna a chave na grafia original), cai em `fallbackOption` quando inválido | `POST .../classify` | follow-up |
| `AiExtractField` | `extract(sourceText): array` — JSON mode opcional, fallback regex `\{[\s\S]*\}` para prosa misturada, filtra keys extras, injeta `null` para keys ausentes | `POST .../extract` | follow-up |
| `AiImageField` | `analyze(imageUrlOrBase64): array<key,string>` — uma chamada por análise declarada via `aiAnalysis`, `populateFields` mapeia para form fields | `POST .../analyze-image` | entregue (`AiImageInput.tsx`) |

Convenções compartilhadas dos fields:
- Prompt template / descriptions **nunca** trafegam para o cliente (segurança/IP). `getTypeSpecificProps()` expõe apenas metadados estruturais (lista de keys, provider, button label, etc.).
- Setters fluentes: `provider(?string)`, `aiOptions(array)`, `buttonLabel(string)` (quando aplicável).
- Authorization: Gate `use-ai` opt-in — quando não definida, allow por default; quando definida e nega, controllers respondem 403.
- Controllers single-action retornam 404 para resource ausente, 422 para field do tipo errado, 422 para `AiException` (parse failure, vision unsupported, etc.).
- `ConfigurableFakeProvider` (test fixture) suporta `textsToReturn[]` (FIFO) + `promptHistory[]` + `optionsHistory[]` para asserts em testes que disparam múltiplas chamadas sequenciais.

**AI-012 — PromptLibrary**
- **`Arqel\Ai\Prompts\PromptLibrary`** (final) — biblioteca de prompt templates reutilizáveis. Métodos estáticos puros que retornam strings (não invocam o provider): `summarize($text, $maxWords=100)`, `translate($text, $target, ?$source=null)`, `classify($text, $categories)` (lista simples OU mapa `key=>label`), `extractJson($text, $schema)` (`field=>description`), `generateSlug($title)`, `keywordExtract($text, $count=5)`, `tone($text, $tone='professional')`, `proofread($text)`. Caller passa o resultado para `AiManager::complete()` quando quiser executar.
- **Custom prompts em runtime** via static map: `register(string $name, Closure $template)` (sobre-escreve silenciosamente), `resolve(string $name, array $data=[])` (lança `InvalidArgumentException` se nome não existe), `has(string $name)`, `clear()` (útil em tests). O closure recebe `array<string,mixed> $data` e retorna `string`.
- **Sem binding no `AiServiceProvider`** — toda a API é estática.

**AI-013 — MCP tools AI-generated**
- Tools registradas em `arqel-dev/mcp` que invocam `AiManager` internamente — primeiro alvo: `generate_resource_from_description` (input: `description, model_name`; output: `resource_code, suggested_path`). Caller é Claude Code/Desktop via MCP server expondo Resources/Forms como tools.
- Cross-package — implementação concreta vive em `arqel-dev/mcp`, mas o contrato de prompt e o uso de `AiManager::complete()` segue as mesmas convenções deste pacote (Gate `use-ai`, cost enforcement, cache opcional).

**AI-014 — Coverage gaps**
- `tests/Unit/Coverage/AiCoverageGapsTest.php` cobre branches que não eram exercitados pelos testes feature/unit existentes: `AiManager::resolveProvider()` com default null; cache hit que **não** chama provider; determinismo de `AiCache::key()`; `CostTracker::record()` com `cost null`; filtro temporal de `getCostSince()`; `ClaudeProvider::estimateCost(0,0) === 0.0`; `OpenAiProvider::chat()` sem system; `OllamaProvider::chat()` sem `eval_count`; `AiTextField::generate()` propagando `AiException`; `AiSelectField` com prosa AI → fallback; `AiTranslateField::translateAll()` no-op quando todas as línguas presentes; `AiExtractField` filtrando keys extras; `PromptLibrary::extractJson([])` com schema vazio.

**AI-015 — SKILL canônico**
- Este arquivo reorganizado para layout canônico (`Purpose / Status / Conventions / Anti-patterns / Examples / Related`) preservando todos os exemplos PT-BR existentes.

**AI-013-ollama — Vision real no OllamaProvider (llava)**
- Ollama agora suporta vision nativa via modelos `llava` / `bakllava` / `llama3.2-vision`. `chat()` envia `messages[].images: [base64,...]` (base64 puro, sem prefixo `data:image/...;base64,`) para `/api/chat`. URLs são baixadas defensivamente via `Http::timeout(5)->get($url)` e convertidas para base64 — falha de download lança `AiException`. Quando há image option mas o `model` configurado é não-vision, o provider faz fallback automático para `visionModel` (default `llava`, override via `ARQEL_AI_OLLAMA_VISION_MODEL` ou parâmetro construtor).
- **`AiProvider::supportsVision(): bool`** adicionado ao contrato. Claude/OpenAi/Ollama retornam `true`; fakes default `false`. Caller usa o flag para gated UI antes de injectar `imageUrl`/`imageBase64` em `options`.

### Por chegar
- **AI-015 follow-up — Docs site**: capítulo "AI fields" em `arqel.dev/docs/ai` cobrindo cada field + anti-patterns. Hoje a doc vive aqui em SKILL.md e nos exemplos PT-BR abaixo.
- **AI-016+ — Streaming SSE end-to-end**: providers já implementam `stream()` (Anthropic SSE, OpenAI SSE, Ollama NDJSON). Falta a ponte React/Inertia para receber chunks num `AiTextField` (provavelmente via endpoint dedicado fora do ciclo Inertia, exposto como rota tipo `text/event-stream`).
- **Fields React follow-up**: `AiTranslateInput.tsx`, `AiSelectInput.tsx`, `AiExtractInput.tsx` (componentes shadcn-styled com botão "Generate" + populate cross-field).

## Conventions

- **Nunca invocar provider sem confirmação do user**. AI tem custo monetário — toda operação iniciada por um field deve passar pelo `confirm` do Action ou flash de "preview before commit". O cost runaway é o anti-padrão #1 deste pacote.
- **Tokens são contabilizados pelo provider, não estimados localmente**. `AiCompletionResult::$inputTokens` / `$outputTokens` vêm da API response. `estimatedCost` é null se o provider não expõe pricing.
- **Provider FQCN guardados como string** em `config/arqel-ai.php` (não `::class`) para evitar resolução eager antes dos concretes existirem.
- **`raw` carrega o payload bruto** do provider. Streaming, tool calls e `finish_reason` ficam aí — não inventamos campos no value-object.
- **Embedding-only ou streaming-only providers DEVEM lançar `AiException`** em métodos não suportados E reportar `supportsEmbeddings()` / `supportsStreaming()` corretamente. Caller usa `supports*` para gated fluxo.
- **Prompt templates ficam server-side**. `getTypeSpecificProps()` nunca expõe a string completa — apenas metadados estruturais (lista de keys, button label, provider). Apps com prompts contendo regras de negócio mantêm-nos privados.
- **Authorization em controllers AI** segue Gate `use-ai` (opt-in). Não definida → allow; definida e nega → 403. Sempre middleware `web,auth`.
- **`AiException` é base RuntimeException** — apps DEVEM capturar e flash ao usuário, nunca deixar vazar 500.

## Anti-patterns

- ❌ **Cost runaway**: chamar AI em hot paths sem cache (`config('arqel-ai.caching.enabled')` é `true` por default) ou esquecer `cost_tracking.per_user_limit_usd`. Limit é hard, não advisory.
- ❌ **Expor prompt template ao client**: `getTypeSpecificProps()` retornar a string completa do prompt vaza IP/regras de negócio. Sempre filtre — apenas metadados estruturais devem cruzar a fronteira PHP↔React.
- ❌ **Depender de provider-specific options sem `supports*` check**: invocar `embed()` direto sem checar `supportsEmbeddings()` (Claude lança `AiException`); invocar `stream()` sem checar `supportsStreaming()`. Use os flags do contrato.
- ❌ **Esquecer Gate `use-ai`**: rotas AI ficam abertas a qualquer user autenticado por default. Em produção, registre a Gate (`Gate::define('use-ai', fn (User $u) => $u->hasRole('editor'))`) ou aceite que todo authenticated pode gastar.
- ❌ **Não capturar `AiException` em UI**: callers que invocam `generate()`/`classify()`/`extract()` direto em controllers user-land devem catch — o pacote nunca esconde a exception, deixar vazar resulta em 500 + page error genérica.
- ❌ Hard-depender de SDKs de provider — todos em `suggest:`, providers usam apenas o `Http` facade do Laravel.
- ❌ Aceitar prompt completo do user sem template/sanitização (prompt injection guard fica no template — `PromptLibrary` é o ponto canônico).
- ❌ Mutar `AiCompletionResult` — é `final readonly`. Faça `new AiCompletionResult(...)` se precisar de uma cópia transformada.

## Examples

### Setup providers + custo

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
    'cost_tracking' => [
        'daily_limit_usd' => 50.0,        // null|<=0 ⇒ ilimitado
        'per_user_limit_usd' => 5.0,      // hard limit por user/dia
    ],
    'caching' => [
        'enabled' => true,
        'ttl' => 3600,                    // segundos
    ],
];
```

Implementar provider customizado:

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

### Field AiText

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

### Field AiTranslate

```php
use Arqel\Ai\Fields\AiTranslateField;

public function fields(): array
{
    return [
        Field::text('title')->required(),

        (new AiTranslateField('description'))
            ->languages(['en', 'pt-BR', 'es'])
            ->defaultLanguage('en')
            ->autoTranslate()
            ->provider('claude')
            ->aiOptions(['temperature' => 0.2]),
    ];
}
```

O valor persiste como JSON `{en: "...", pt-BR: "...", es: "..."}`. Configure
o cast no model:

```php
protected function casts(): array
{
    return ['description' => 'array'];
}
```

Quando `autoTranslate()` está ativo, o React (follow-up) dispara
`POST /admin/{slug}/fields/description/translate` com o texto do
`defaultLanguage` e os idiomas restantes; o backend chama `translate()`
para cada idioma alvo. Traduções manuais já preenchidas **não são
sobrescritas** por `translateAll()` — só os campos vazios são gerados.

### Field AiSelect classify

```php
use Arqel\Ai\Fields\AiSelectField;

public function fields(): array
{
    return [
        Field::text('title')->required(),
        Field::textarea('description'),

        (new AiSelectField('category'))
            ->options([
                'tech' => 'Technology',
                'finance' => 'Finance',
                'health' => 'Health',
            ])
            ->classifyFromFields(['title', 'description'])
            ->prompt('Classify this article. Title: {title}. Description: {description}.')
            ->fallbackOption('tech')
            ->provider('claude'),
    ];
}
```

O React (follow-up) dispara `POST /admin/{slug}/fields/category/classify`
com o `formData` atual; o backend chama `classify()` e devolve
`{key, label}`. Quando a AI retorna uma key fora do set declarado, o
resultado cai em `fallbackOption` — sem `fallbackOption()`, o response
é `{key: null, label: null}` e o select fica sem seleção.

### Field AiExtract JSON mode

```php
use Arqel\Ai\Fields\AiExtractField;

(new AiExtractField('extracted'))
    ->sourceField('raw_text')
    ->extractTo([
        'total' => 'Valor total com símbolo da moeda',
        'due_date' => 'Data de vencimento em ISO',
        'invoice_number' => 'Número da nota fiscal — pode estar ausente',
    ])
    ->usingJsonMode()                  // OpenAI: response_format=json_object
    ->provider('openai');
```

`extract($sourceText)` devolve um array com **apenas** as keys declaradas em
`extractTo`; keys extras vindas da AI são filtradas, keys ausentes recebem
`null` (consumidor distingue "AI omitiu" de "key não esperada"). Falha de
parse mesmo após o fallback regex `\{[\s\S]*\}` lança `AiException`.

### PromptLibrary custom register

Templates reutilizáveis built-in (não invocam o provider — retornam apenas a string do prompt):

```php
use Arqel\Ai\Prompts\PromptLibrary;

$prompt = PromptLibrary::summarize($post->body, maxWords: 80);
$result = app(\Arqel\Ai\AiManager::class)->complete($prompt);

// Tradução com par explícito
$prompt = PromptLibrary::translate($text, targetLanguage: 'pt-BR', sourceLanguage: 'en');

// Classificação com mapa key=>label (modelo retorna a key)
$prompt = PromptLibrary::classify($body, [
    'tech' => 'Technology',
    'finance' => 'Finance',
]);

// Extração JSON estruturada
$prompt = PromptLibrary::extractJson($invoiceText, [
    'total' => 'Total amount with currency symbol',
    'due_date' => 'Due date in ISO format',
]);

// Outros built-ins
PromptLibrary::generateSlug('Hello World!');
PromptLibrary::keywordExtract($text, count: 7);
PromptLibrary::tone($text, tone: 'casual');
PromptLibrary::proofread($text);
```

Custom prompts registráveis em runtime (ex.: em um `ServiceProvider::boot()`):

```php
PromptLibrary::register('company_bio', function (array $data): string {
    return "Write a 2-paragraph bio for {$data['company_name']} in {$data['industry']}.";
});

$prompt = PromptLibrary::resolve('company_bio', [
    'company_name' => 'Arqel',
    'industry' => 'Developer Tools',
]);
```

`resolve()` lança `InvalidArgumentException` quando o nome não está registrado;
`has()` faz a checagem prévia. `clear()` é útil para isolamento entre testes.

### MCP tool integration

`arqel-dev/mcp` registra tools que chamam `AiManager` internamente. O exemplo
canônico (AI-013) é "gerar Resource a partir de descrição":

```php
final class GenerateResourceFromDescriptionTool
{
    public function schema(): array
    {
        return [
            'name' => 'generate_resource_from_description',
            'description' => 'Generate an Arqel Resource class from natural language description',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'description' => ['type' => 'string'],
                    'model_name' => ['type' => 'string'],
                ],
                'required' => ['description', 'model_name'],
            ],
        ];
    }

    /** @param array{description: string, model_name: string} $params */
    public function __invoke(array $params): array
    {
        $prompt = $this->buildPrompt($params['description'], $params['model_name']);
        $result = app(\Arqel\Ai\AiManager::class)->complete($prompt);

        return [
            'resource_code' => $result->text,
            'suggested_path' => "app/Arqel/Resources/{$params['model_name']}Resource.php",
        ];
    }
}
```

A chamada respeita as mesmas convenções deste pacote: Gate `use-ai`, cost
tracking, cache opcional. Caller (Claude Code/Desktop via MCP) recebe o
código gerado e decide se grava no path sugerido.

### Vision com Ollama (llava local)

```php
use Arqel\Ai\Providers\OllamaProvider;

// Por URL — provider baixa via Http::timeout(5) e converte para base64
app(OllamaProvider::class)->chat([
    ['role' => 'user', 'content' => 'What animal is in this image?'],
], ['imageUrl' => 'https://example.com/cat.jpg']);

// Por base64 (data URI ou base64 puro — provider faz strip do prefixo)
app(OllamaProvider::class)->chat([
    ['role' => 'user', 'content' => 'Describe this'],
], ['imageBase64' => 'data:image/png;base64,iVBORw0KGgo...']);

// Override explícito do model vision
app(OllamaProvider::class)->chat($messages, [
    'imageUrl' => 'https://example.com/x.jpg',
    'model' => 'llama3.2-vision:latest',
]);
```

Quando `chat()` recebe vision option mas o model configurado é não-vision (ex.:
`llama3.1`), o provider faz fallback automático para o `visionModel`
(default `llava`). Configure via `ARQEL_AI_OLLAMA_VISION_MODEL` ou parâmetro
do construtor `visionModel:`.

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

- `arqel-dev/core` — `Resource` lifecycle hooks que vão dispatch AI calls de afterCreate/afterUpdate (futuro).
- `arqel-dev/fields` — base `Field` que `AiTextField` etc. estendem.
- `arqel-dev/mcp` — AI-013 expõe Resource analysis como MCP tools para Claude Desktop / Claude Code.
- `PLANNING/10-fase-3-avancadas.md` §2 (AI fields) — roadmap completo + tickets AI-001..AI-016.

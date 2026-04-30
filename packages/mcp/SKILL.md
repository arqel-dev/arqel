# SKILL.md — arqel/mcp

> Contexto canônico para AI agents.

## Purpose

`arqel/mcp` expõe um servidor [Model Context Protocol](https://modelcontextprotocol.io) sobre os panels Arqel: tools (executar Actions, mutar Resources), resources (ler dados de Resource/Table/Widget), prompts (templates pré-construídos para fluxos comuns de admin). Permite que clientes MCP — Claude Desktop, Cursor, Zed, agents customizados — operem o painel via JSON-RPC com a mesma authorização e validação que os usuários humanos vêem na UI Inertia.

A escolha é **aderir ao spec do protocol**: nenhum desvio de `modelcontextprotocol.io`; o pacote é só a tradução PHP/Laravel das primitivas (Tool, Resource, Prompt) e o glue que descobre Resources/Actions já registrados em `arqel/core`.

## Status

**Entregue (MCP-001):**

- Esqueleto do pacote (`composer.json`, PSR-4 `Arqel\Mcp\` → `src/`, dep em `arqel/core` via path repo)
- `McpServiceProvider` registado via auto-discovery (`extra.laravel.providers`)
- Pest 3 + Orchestra Testbench setup com `defineEnvironment` SQLite in-memory

**Entregue (MCP-002):**

- **`McpServer` (final)** — handler JSON-RPC 2.0 completo aderente ao spec MCP `2024-11-05`. API pública:
  - **Registro**: `registerTool($name, $description, $inputSchema, $handler)`, `registerResource($uri, $name, $description, $fetcher)`, `registerPrompt($name, $description, $arguments, $generator)` persistem metadata + callable em mapas privados (chave: `name` para tools/prompts, `uri` para resources)
  - **Introspecção**: `getTools()`/`getResources()`/`getPrompts()` devolvem o mapa de metadata SEM o callable (handlers são runtime-only)
  - **Dispatch**: `handleRequest(array $request): array` — público para permitir testes unitários sem stdio. Implementa: `initialize`, `tools/list`, `tools/call`, `resources/list`, `resources/read`, `prompts/list`, `prompts/get`
  - **Loop**: `serve()` — wrapper fino do loop stdio newline-delimited (lê `fgets(STDIN)` → `handleRequest` → escreve em `STDOUT`). Não testado diretamente (bound a stdio); cobertura vem via `handleRequest`
- **Envelope JSON-RPC**:
  - Resposta sempre carrega `{jsonrpc: '2.0', id: <request.id>}` + `result` OU `error: {code, message, data?}`
  - Notifications (request sem `id`) NÃO recebem resposta — `handleRequest` retorna `[]`
  - Códigos de erro: `-32600` Invalid Request (envelope malformado, faltando `method` ou `jsonrpc !== '2.0'`), `-32601` Method not found (método desconhecido), `-32602` Invalid params (tool/resource/prompt name/uri desconhecido), `-32603` Internal error (handler lança Throwable; `data.exception` carrega a mensagem original)
  - Resultado de tool é wrapped em `{content: [{type: 'text', text: <stringified>}]}`; strings passam direto, arrays/objetos são `json_encode`-ados
  - Resultado de resource é wrapped em `{contents: [{uri, text, mimeType?}]}`
  - Resultado de prompt é wrapped em `{description, messages: <generator output>}` — generator é responsável por devolver o array de messages no formato MCP
- **24 testes Pest passando** (4 Feature + 20 Unit) cobrindo: registro persiste e excluí callables, dispatch de cada método, wrapping de resultados (string + array + objeto), erros `-32600..-32603`, notifications retornam `[]`, preservação de `id` numérico/string

**Entregue (MCP-003):**

- **`Tools\ListResourcesTool` (final)** — primeira tool exposta. `schema()` devolve o envelope MCP (`name=list_resources`, `inputSchema={type:object, properties:[]}`); `__invoke(array)` resolve `Arqel\Core\Resources\ResourceRegistry` via `Container::getInstance()->make(...)` (ou via construtor `?Closure $resolver = null` que devolve `array<int, class-string>` — usado em testes para evitar o `final ResourceRegistry` + o type-guard `HasResource`) e mapeia cada class-string para `{class, model, slug, label, pluralLabel, navigationGroup}` chamando os 5 metadata estáticos
- **Defensiva**: cada resource é embrulhado em `try/catch \Throwable` — uma Resource parcialmente definida durante boot é silenciosamente ignorada, não derruba a tool
- **Auto-registro**: `McpServiceProvider::packageBooted()` instancia a tool, lê o schema e chama `$server->registerTool($name, $description, $inputSchema, $handler)` para que `list_resources` apareça em `tools/list` sem qualquer setup extra do panel
- **5 testes unitários** + **2 testes de feature** novos cobrindo: schema canônico, serialização completa de 2 fixtures, registry vazio, fixture que joga em `getLabel` é skipped, fallback ao container, auto-registro via boot do package, dispatch via `tools/call`

**Entregue (MCP-004):**

- **`Tools\DescribeResourceTool` (final)** — segunda tool exposta. `schema()` devolve o envelope MCP (`name=describe_resource`, `description="Get detailed information about a specific Arqel Resource"`, `inputSchema={type:object, properties:{slug:{type:string, description:...}}, required:[slug]}`); `__invoke(array)` valida `slug` (string obrigatória → `InvalidArgumentException`), resolve via `Container::getInstance()->make(ResourceRegistry::class)->findBySlug($slug)` (ou via construtor `?Closure $resolver = null` com signature `(string $slug): ?class-string` — usado em testes para contornar o `final ResourceRegistry` + type-guard `HasResource`), e devolve payload estático com 8 chaves: `class`, `model`, `slug`, `label`, `pluralLabel`, `navigationIcon`, `navigationGroup`, `navigationSort`. Slug desconhecido → `RuntimeException` com a slug na mensagem
- **Defensiva por campo**: `class`/`slug`/`model` são estritos (propagam exceção — Resource que falha neles é inutilizável); restantes campos opcionais são `try/catch`-ados — nulláveis (`navigationIcon`/`navigationGroup`/`navigationSort`) degradam para `null`, não-nulláveis (`label`/`pluralLabel`) degradam para a `getMessage()` da exceção. Permite descrever Resources parcialmente quebrados sem derrubar a tool
- **Auto-registro**: `McpServiceProvider::packageBooted()` instancia a tool ao lado de `ListResourcesTool` e chama `$server->registerTool(...)` com schema + handler — segue o mesmo padrão de MCP-003
- **Out-of-scope** (chega em MCP-005+): introspecção de fields, table columns, actions e policy do Resource — exigem instanciar o Resource e walk de form/table; MCP-004 entrega só o payload de metadata estático
- **6 testes novos** (5 unit + 1 feature): schema canônico, payload completo das 8 chaves para slug conhecido, `InvalidArgumentException` para slug ausente e não-string, `RuntimeException` para slug desconhecido, defensiva (icon throws → null + outros campos populados), auto-registo conjunto de `describe_resource` + `list_resources` no boot

**Entregue (MCP-005):**

- **`Tools\GenerateResourceTool` (final)** — terceira tool exposta; wrapper do Artisan `arqel:resource`. `schema()` devolve `name=generate_resource`, `description="Generate a new Arqel Resource for an Eloquent model"`, `inputSchema={type:object, properties:{model:string, fromModel:boolean (default true), withPolicy:boolean (default true)}, required:[model]}`. `__invoke(array)` valida `model` (string obrigatória → `InvalidArgumentException`), monta `args = {model, --from-model, --with-policy}` e devolve payload `{model, exitCode, output, success}` (`success === exitCode === 0`)
- **Closure runner (testabilidade)**: construtor aceita `?Closure $runner = null` com signature `(array): array{exitCode: int, output: string}` — testes injetam closure que captura args e devolve resultado mock, evitando boot completo do Artisan + filesystem real. Quando `null`, fallback default delega para `Container::getInstance()->make(Kernel::class)->call('arqel:resource', $args)` + `Artisan::output()`. Mesmo padrão de injeção dos resolvers de MCP-003/004 (`final` no domain class, behavior swap via Closure)
- **Divergência vs spec**: o `MakeResourceCommand` real (assinatura `arqel:resource {model} {--with-policy} {--force}`) não tem `--from-model`. O schema MCP ainda expõe `fromModel` para forward-compat; o default runner remove `--from-model` antes de chamar Artisan para não disparar "unknown option". Custom runners injetados em testes recebem o flag tal qual — assim o LLM pode introspeccionar o que foi pedido
- **Auto-registro**: `McpServiceProvider::packageBooted()` agora instancia `ListResourcesTool` + `DescribeResourceTool` + `GenerateResourceTool` (3 tools built-in), todas via mesmo padrão `$server->registerTool($name, $description, $inputSchema, $handler)`
- **7 testes novos** (6 unit + 1 feature): schema canônico, happy path com captura de args, `InvalidArgumentException` para `model` ausente e não-string, passthrough de `fromModel=false` + `withPolicy=false`, falha (`exitCode=1` → `success=false`), auto-registo das 3 tools no boot

**Entregue (MCP-006):**

- **`Tools\RunTestTool` (final)** — quarta tool exposta; wrapper de Pest/PHPUnit para fluxo TDD com o LLM no loop. `schema()` devolve `name=run_test`, `description="Run Pest or PHPUnit tests with optional filter"`, `inputSchema={type:object, properties:{filter:string, path:string, coverage:boolean (default false)}}` — **sem chave `required`** (todos os parâmetros são opcionais). `__invoke(array)` monta `cmd = ['./vendor/bin/pest']`, anexa `--filter={value}` se `filter` for string não-vazia, anexa `path` posicional, anexa `--coverage` se a flag for `true`, e devolve payload `{exitCode, output, errorOutput, success, command}`
- **Validação de segurança do `path`**: rejeita paths absolutos (`str_starts_with($path, '/')`) e qualquer `..` (`str_contains($path, '..')`) com `InvalidArgumentException`. Bloqueia escape do diretório do projeto antes mesmo de invocar o runner
- **Timeout clamp**: parâmetro `timeout` (segundos) é clampado ao intervalo inclusivo `[1, 600]`; default 300s
- **Closure runner**: construtor aceita `?Closure $runner = null` para testabilidade. Default runner usa `Symfony\Component\Process\Process`. Mesmo padrão dos outros 3 tools
- **Auto-registro**: `McpServiceProvider::packageBooted()` agora registra **4 tools** built-in (`list_resources`, `describe_resource`, `generate_resource`, `run_test`)
- **10 testes novos** (9 unit + 1 feature)

**Entregue (MCP-007):**

- **`Resources\SkillResource` (final)** — primeiro MCP Resource exposto. URI scheme: `arqel-skill://<package>` onde `<package>` casa `[a-z0-9-]+` e referencia uma pasta sob `packages/` no monorepo (ex.: `arqel-skill://core` → `packages/core/SKILL.md`). API:
  - `list(): array` — devolve lista de entries `{uri, name, description, mimeType: 'text/markdown'}`, uma por package descoberto
  - `read(string $uri): array` — valida URI via regex `^arqel-skill://([a-z0-9-]+)$` (uppercase, slashes ou outros caracteres → `RuntimeException('Invalid URI: ...')`), lê o `SKILL.md` correspondente e devolve no envelope MCP `{contents: [{uri, mimeType, text}]}`. Erro de leitura é re-embrulhado como `RuntimeException('SKILL.md not found for arqel/<package>')`
- **Closure injection (testabilidade)**: construtor aceita `?Closure $packagesResolver = null` e `?Closure $contentReader = null` — testes injetam closures e bypassam filesystem. Default discover via `glob(<root>/packages/*/SKILL.md)`
- **Path resolution**: tenta `Container::getInstance()->make('path.base')` primeiro e cai para `realpath(__DIR__/../../../..)` quando o `base_path` do host não tiver `packages/`. Permite dogfooding do monorepo sem config manual
- **Auto-registro pre-flattened**: `packageBooted()` chama `SkillResource::list()` UMA vez no boot e registra cada entry como resource individual no `McpServer`. Restart necessário se SKILL.md são adicionados em runtime
- **11 testes novos** (9 unit + 2 feature)

**Entregue (MCP-008):**

- **`Prompts\MigrateFilamentResourcePrompt` (final)** e **`Prompts\ReviewResourcePrompt` (final)** — primeiros MCP Prompts expostos. Templates pré-construídos que ajudam o LLM a (1) migrar uma Resource Filament para Arqel e (2) revisar uma Resource Arqel buscando code smells, missing fields/actions/policies, riscos de N+1, gaps de validação e relacionamentos faltando. API:
  - `schema(): array` — devolve `{name, description, arguments: [{name, description, required: true}]}`. Argumentos: `filament_file` (migrate) e `resource_file` (review), ambos paths relativos à raiz do projeto
  - `generate(array $args): array` — devolve `{description, messages: [{role: 'user', content: [{type: 'text', text: <prompt>}]}]}`. Inlina o conteúdo do PHP source dentro de um fenced code block ` ```php ... ``` ` seguido das diretrizes de migração/review
- **Closure injection (testabilidade)**: construtor aceita `?Closure $fileReader = null` com signature `(string $relativePath): string`. Testes injetam closures que devolvem fixtures inline; default reader resolve via `Container::getInstance()->make('path.base').'/'.$relativePath` + `realpath` + `file_get_contents`. Mesmo padrão de `Resources\SkillResource` (MCP-007) e dos 4 tools (MCP-003..006)
- **Path traversal guard**: `str_contains($relativePath, '..')` → `InvalidArgumentException` ANTES de qualquer chamada ao reader. Argumento ausente, vazio ou não-string também → `InvalidArgumentException`. Erros do reader são propagados (RuntimeException com path na mensagem)
- **Auto-registro**: `McpServiceProvider::packageBooted()` instancia ambos prompts e chama `$server->registerPrompt($schema['name'], $schema['description'], $schema['arguments'], static fn (array $args) => $prompt->generate($args)['messages'])`. Note que `McpServer::handleRequest('prompts/get')` já wrapa `{description, messages}` automaticamente — o closure registado devolve apenas o `messages` array
- **Total de built-ins** após MCP-008: **4 tools** (`list_resources`, `describe_resource`, `generate_resource`, `run_test`) + **N skill resources** (1 por package com SKILL.md) + **2 prompts** (`migrate_filament_resource`, `review_resource`)
- **15 testes novos** (13 unit em `tests/Unit/Prompts/` + 3 feature em `tests/Feature/PromptsRegistrationTest.php`); suite total agora **83 testes**, 258 asserções

**Por chegar (MCP-009..010):**

- Artisan `arqel:mcp:serve` envolvendo `McpServer::serve()` — followup de MCP-002
- Auth (token bearer + tenant scoping via `arqel/tenant`) — MCP-009
- Streaming responses para tools de longa duração — MCP-009
- Manifest publishing (`mcp.json` para Claude Desktop autoinstall) — MCP-010

## Conventions

- `declare(strict_types=1)` obrigatório
- Classes `final` por padrão; quando MCP-002 trouxer drivers de transport, abstract bases ficam não-final em `src/Transport/`
- **Spec-first**: nenhum método/payload diverge do JSON Schema oficial em `modelcontextprotocol.io`. Quando o spec evolui, este pacote acompanha — não estende
- **Sem hard dep** em SDKs MCP de terceiros: o JSON-RPC handler em MCP-002 será implementação própria minimal (PSR-7 like) para não acoplar a uma lib que ainda está em flux

## Anti-patterns

- ❌ **Inventar tools/resources fora do spec MCP** — se o cliente Claude Desktop não consegue parsear, é bug nosso. Toda extensão precisa virar pull request upstream em `modelcontextprotocol.io` antes
- ❌ **Reusar HTTP middleware do Laravel para auth de MCP sessions** — sessions MCP são JSON-RPC de longa duração, não requests Inertia; auth precisa de pipeline próprio (token-bound, sem CSRF)
- ❌ **Expor todos os Resources do panel automaticamente** — opt-in explícito via `Resource::exposeToMcp()` ou flag em config. Modelos sensíveis (User, Tenant billing) nunca devem ser readable por default

## Related

- Tickets: [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §MCP-001..010
- Source: [`packages/mcp/src/`](./src/)
- Tests: [`packages/mcp/tests/`](./tests/)
- ADRs:
  - [ADR-001](../../PLANNING/03-adrs.md) — Inertia-only (MCP é canal paralelo, não substitui)
  - [ADR-008](../../PLANNING/03-adrs.md) — Pest 3
- Externos: [Model Context Protocol spec](https://modelcontextprotocol.io)

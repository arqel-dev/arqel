# AGENTS.md (LLMs friendly)

`AGENTS.md` é o standard [agents.md](https://agents.md/) que fornece contexto canónico para qualquer agente AI (Claude Code, Cursor, Windsurf, Aider, etc.) trabalhando num projeto. Arqel **gera** um automaticamente em `arqel:install`.

## Por que isso importa

LLMs precisam de contexto explícito sobre stack, convenções e onde encontrar canonical truth. Sem `AGENTS.md`:

- O agente reinventa convenções já decididas
- Sugere libs incompatíveis (ex: TanStack Query num projeto Inertia-only)
- Ignora os SKILL.md dos pacotes
- Faz commits sem DCO, sem Conventional Commits, em inglês quando devia ser PT-BR

Com `AGENTS.md`, o agente lê uma vez no início da sessão e mantém-se aderente.

## O que Arqel gera

`php artisan arqel:install` cria `AGENTS.md` na raiz do projeto user com 7 seções:

### 1. Projeto

```markdown
**Nome:** Acme Admin
**Descrição:** Admin panel para sistema X
**Stack:** Laravel 12 + Inertia 3 + React 19 + Arqel
```

### 2. Stack

Lista versões mínimas (PHP 8.3+, Node 20.9+, etc.) e libs principais. **Crítico:** menciona explicitamente que **Inertia é a única bridge PHP↔React permitida** ([ADR-001](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)) — evita o agente sugerir TanStack Query.

### 3. Comandos frequentes

```bash
composer install && pnpm install
php artisan serve
pnpm dev
vendor/bin/pest
pnpm test
vendor/bin/pint
pnpm lint
```

### 4. Convenções obrigatórias

- Linguagem: inglês para código, PT-BR para docs/comunicação
- `declare(strict_types=1)` em todos PHP files
- Classes `final` por defeito
- Conventional Commits + DCO sign-off
- Tests-first (sem PR sem testes)

### 5. Estrutura

```
app/
  Arqel/
    Resources/   # Resources Arqel
    Widgets/     # Dashboard widgets
  Models/        # Eloquent
  Policies/      # Laravel Policies
resources/
  js/
    Pages/Arqel/   # Inertia pages (overrides Arqel defaults)
    Arqel/Fields/  # Custom fields React
  css/app.css      # @import 'tailwindcss' + @arqel-dev/ui
```

### 6. Architecture summary

Resume RF/RNF principais e aponta para `docs/` interno e SKILLs dos pacotes Arqel:

- `vendor/arqel-dev/core/SKILL.md`
- `vendor/arqel-dev/fields/SKILL.md`
- `vendor/arqel-dev/table/SKILL.md`
- ...

### 7. Links

- Site da documentação Arqel
- Source no GitHub
- ADRs canónicos
- Slack/Discord da comunidade

## Como customizar

`AGENTS.md` é seu — Arqel só **inicializa**. Edite à vontade:

- Adicione contexto de domínio (vocabulário do business)
- Liste libs específicas do app (ex: spatie/laravel-permission)
- Documente convenções internas (ex: "todos jobs são `ShouldQueue`")
- Aponte para playbooks internos

::: tip Versionar
Faça commit do `AGENTS.md` no repo — assim cada agente que clona vê o mesmo contexto. **Não** o adicione ao `.gitignore`.
:::

## Template completo

Você pode reproduzir o template raw a partir do source:

```bash
# No source do Arqel
cat packages/core/stubs/agents.stub
```

Ou ver o `AGENTS.md` do próprio monorepo Arqel como referência:

- [`AGENTS.md` no GitHub](https://github.com/arqel-dev/arqel/blob/main/AGENTS.md)

## MCP — Model Context Protocol (stub)

Em paralelo ao `AGENTS.md`, Arqel expõe um **MCP server** (stub na Fase 1, full em Fase 2) que permite a LLMs **explorar o panel em runtime**:

```ts
// Phase 2 preview
import { ArqelMcpServer } from '@arqel-dev/mcp';

const server = new ArqelMcpServer({ panel: 'admin' });
// Tools: list-resources, get-resource-fields, list-actions, ...
```

Tools planeadas:

- `list-resources` — retorna `[{ slug, label, model }]`
- `get-resource-fields(slug)` — schema dos fields do Resource
- `list-actions(slug)` — actions disponíveis
- `query-resource(slug, filters?, sort?, perPage?)` — preview do index payload
- `inspect-policy(slug)` — métodos da Policy + seus checks

Hoje o agente lê `AGENTS.md` + SKILL.md estaticamente. Em Fase 2, o MCP server permite query dinâmica — `"quais fields o PostResource expõe agora?"` retorna o schema vivo via stdio JSON-RPC.

## Links relacionados

- [agents.md](https://agents.md/) — standard comunitário
- [`packages/core/stubs/agents.stub`](https://github.com/arqel-dev/arqel/blob/main/packages/core/stubs/agents.stub)
- [Roadmap Fase 2 — MCP](https://github.com/arqel-dev/arqel/blob/main/PLANNING/09-fase-2-essenciais.md)
- [Custom Fields](/advanced/custom-fields) — pattern que LLMs usam pra gerar custom field types

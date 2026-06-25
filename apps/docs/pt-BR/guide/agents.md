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

`php artisan arqel:install` cria `AGENTS.md` na raiz do projeto user com 5 seções:

### 1. Project overview

Nomeia o app, sua versão do Arqel e a stack (PHP, Laravel, Inertia 3 + React 19 + Tailwind v4), e aponta onde vivem os Resources e as pages Inertia:

```markdown
Esta aplicação usa **Arqel** — admin panels declarativos em PHP, renderizados
em React via Inertia.

- Arqel Resources vivem em `app/Arqel/Resources/`
- Pages Inertia geradas em `resources/js/Pages/Arqel/`
```

### 2. Key conventions

- **Inertia-only:** nunca adicionar TanStack Query, SWR, ou outras fetch libs para CRUD de Resources ([ADR-016](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md))
- Resources são a fonte da verdade — a UI deriva da definição em PHP
- Tests-first (Pest para PHP + Vitest para JS)
- Classes `final` por defeito
- `declare(strict_types=1)` em todo PHP file novo

### 3. Commands

```bash
# Scaffold de um novo Resource
php artisan arqel:resource <Model>

# Rodar testes
vendor/bin/pest
npm run test

# Build / dev
npm run build
npm run dev
php artisan serve
```

### 4. Architecture

Uma árvore do layout do projeto — `app/Arqel/Resources` e `Widgets`, o `ArqelServiceProvider`, `config/arqel.php` e `resources/js/Pages/Arqel/`:

```
app/
├── Arqel/
│   ├── Resources/      ← Definições de Resource (CRUD declarativo)
│   └── Widgets/        ← Widgets de dashboard
config/
└── arqel.php           ← Path, guard, namespaces
resources/js/
└── Pages/Arqel/        ← Pages Inertia (auto-resolvidas)
```

### 5. Recursos adicionais

- Site da documentação Arqel
- Issues no GitHub
- O SKILL.md de cada pacote Arqel em `vendor/arqel-dev/*/SKILL.md`

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

## MCP — Model Context Protocol

Em paralelo ao `AGENTS.md`, Arqel entrega um **MCP server** que permite a LLMs explorar o framework e o panel. Ele roda via stdio e é publicado no npm como `@arqel-dev/mcp-server`:

```jsonc
// .mcp.json / config do Claude Desktop
{
  "mcpServers": {
    "arqel": {
      "command": "npx",
      "args": ["-y", "@arqel-dev/mcp-server"]
    }
  }
}
```

Ele expõe 7 tools:

- `search_docs` — busca full-text na documentação
- `get_adr` — busca um ADR canônico por número
- `get_api_reference` — consulta um símbolo da API PHP/React
- `list_resources` — lista os Resources registrados no projeto
- `describe_resource` — fields, table e form schema de um Resource
- `generate_resource` — gera o scaffold de um novo Resource
- `generate_field` — gera o scaffold de um field custom

O lado PHP (composer `arqel-dev/mcp`) implementa o core JSON-RPC do `McpServer` mais os registries de tool/resource/prompt. O único follow-up ainda pendente é o comando Artisan `arqel:mcp:serve`; até ele chegar, integradores podem chamar `McpServer::serve()` a partir de um script custom. Veja o [guia do MCP server](/pt-BR/guide/mcp-server) para o setup completo.

## Links relacionados

- [agents.md](https://agents.md/) — standard comunitário
- [`packages/core/stubs/agents.stub`](https://github.com/arqel-dev/arqel/blob/main/packages/core/stubs/agents.stub)
- [Roadmap Fase 2 — MCP](https://github.com/arqel-dev/arqel/blob/main/PLANNING/09-fase-2-essenciais.md)
- [Custom Fields](/pt-BR/advanced/custom-fields) — pattern que LLMs usam pra gerar custom field types

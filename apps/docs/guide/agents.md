# AGENTS.md (LLM-friendly)

`AGENTS.md` is the [agents.md](https://agents.md/) standard that provides canonical context to any AI agent (Claude Code, Cursor, Windsurf, Aider, etc.) working on a project. Arqel **generates** one automatically in `arqel:install`.

## Why this matters

LLMs need explicit context about stack, conventions, and where to find canonical truth. Without `AGENTS.md`:

- The agent reinvents conventions that have already been decided
- Suggests incompatible libs (e.g. TanStack Query in an Inertia-only project)
- Ignores the SKILL.md files of packages
- Makes commits without DCO, without Conventional Commits, in English when it should be PT-BR

With `AGENTS.md`, the agent reads it once at the start of the session and stays consistent.

## What Arqel generates

`php artisan arqel:install` creates an `AGENTS.md` at the user project root with 5 sections:

### 1. Project overview

Names the app, its Arqel version, and the stack (PHP, Laravel, Inertia 3 + React 19 + Tailwind v4), and points out where Resources and Inertia pages live:

```markdown
Esta aplicação usa **Arqel** — admin panels declarativos em PHP, renderizados
em React via Inertia.

- Arqel Resources vivem em `app/Arqel/Resources/`
- Pages Inertia geradas em `resources/js/Pages/Arqel/`
```

### 2. Key conventions

- **Inertia-only:** never add TanStack Query, SWR, or other fetch libs for Resource CRUD ([ADR-016](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md))
- Resources are the source of truth — the UI derives from the PHP definition
- Tests-first (Pest for PHP + Vitest for JS)
- Classes `final` by default
- `declare(strict_types=1)` in every new PHP file

### 3. Commands

```bash
# Scaffold a new Resource
php artisan arqel:resource <Model>

# Run tests
vendor/bin/pest
npm run test

# Build / dev
npm run build
npm run dev
php artisan serve
```

### 4. Architecture

A tree of the project layout — `app/Arqel/Resources` and `Widgets`, the `ArqelServiceProvider`, `config/arqel.php`, and `resources/js/Pages/Arqel/`:

```
app/
├── Arqel/
│   ├── Resources/      ← Resource definitions (declarative CRUD)
│   └── Widgets/        ← Dashboard widgets
config/
└── arqel.php           ← Path, guard, namespaces
resources/js/
└── Pages/Arqel/        ← Inertia pages (auto-resolved)
```

### 5. Recursos adicionais

- Arqel documentation site
- GitHub issues
- The SKILL.md of each Arqel package under `vendor/arqel-dev/*/SKILL.md`

## How to customize

`AGENTS.md` is yours — Arqel only **initializes** it. Edit freely:

- Add domain context (business vocabulary)
- List app-specific libs (e.g. spatie/laravel-permission)
- Document internal conventions (e.g. "every job is `ShouldQueue`")
- Point to internal playbooks

::: tip Version it
Commit `AGENTS.md` to the repo — that way every agent that clones sees the same context. **Don't** add it to `.gitignore`.
:::

## Full template

You can reproduce the raw template from the source:

```bash
# In the Arqel source
cat packages/core/stubs/agents.stub
```

Or look at the Arqel monorepo's own `AGENTS.md` as a reference:

- [`AGENTS.md` on GitHub](https://github.com/arqel-dev/arqel/blob/main/AGENTS.md)

## MCP — Model Context Protocol

Alongside `AGENTS.md`, Arqel ships an **MCP server** that lets LLMs explore the framework and the panel. It runs over stdio and is published on npm as `@arqel-dev/mcp-server`:

```jsonc
// .mcp.json / Claude Desktop config
{
  "mcpServers": {
    "arqel": {
      "command": "npx",
      "args": ["-y", "@arqel-dev/mcp-server"]
    }
  }
}
```

It exposes 7 tools:

- `search_docs` — full-text search across the docs
- `get_adr` — fetch a canonical ADR by number
- `get_api_reference` — look up a PHP/React API symbol
- `list_resources` — list the Resources registered in the project
- `describe_resource` — fields, table and form schema of a Resource
- `generate_resource` — scaffold a new Resource
- `generate_field` — scaffold a custom field

The PHP side (composer `arqel-dev/mcp`) implements the `McpServer` JSON-RPC core plus tool/resource/prompt registries. The only follow-up still pending is the `arqel:mcp:serve` Artisan command; until it lands, integrators can call `McpServer::serve()` from a custom script. See the [MCP server guide](/guide/mcp-server) for the full setup.

## Related links

- [agents.md](https://agents.md/) — community standard
- [`packages/core/stubs/agents.stub`](https://github.com/arqel-dev/arqel/blob/main/packages/core/stubs/agents.stub)
- [Roadmap Phase 2 — MCP](https://github.com/arqel-dev/arqel/blob/main/PLANNING/09-fase-2-essenciais.md)
- [Custom Fields](/advanced/custom-fields) — pattern LLMs use to generate custom field types

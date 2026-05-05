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

`php artisan arqel:install` creates an `AGENTS.md` at the user project root with 7 sections:

### 1. Project

```markdown
**Name:** Acme Admin
**Description:** Admin panel for system X
**Stack:** Laravel 12 + Inertia 3 + React 19 + Arqel
```

### 2. Stack

Lists minimum versions (PHP 8.3+, Node 20.9+, etc.) and main libs. **Critical:** explicitly mentions that **Inertia is the only allowed PHP↔React bridge** ([ADR-001](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)) — preventing the agent from suggesting TanStack Query.

### 3. Frequent commands

```bash
composer install && pnpm install
php artisan serve
pnpm dev
vendor/bin/pest
pnpm test
vendor/bin/pint
pnpm lint
```

### 4. Required conventions

- Language: English for code, PT-BR for docs/communication
- `declare(strict_types=1)` in every PHP file
- Classes `final` by default
- Conventional Commits + DCO sign-off
- Tests-first (no PR without tests)

### 5. Structure

```
app/
  Arqel/
    Resources/   # Arqel Resources
    Widgets/     # Dashboard widgets
  Models/        # Eloquent
  Policies/      # Laravel Policies
resources/
  js/
    Pages/Arqel/   # Inertia pages (overrides Arqel defaults)
    Arqel/Fields/  # Custom React fields
  css/app.css      # @import 'tailwindcss' + @arqel-dev/ui
```

### 6. Architecture summary

Summarizes the main RF/RNF and points to internal `docs/` and the SKILLs of Arqel packages:

- `vendor/arqel-dev/core/SKILL.md`
- `vendor/arqel-dev/fields/SKILL.md`
- `vendor/arqel-dev/table/SKILL.md`
- ...

### 7. Links

- Arqel documentation site
- GitHub source
- Canonical ADRs
- Community Slack/Discord

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

## MCP — Model Context Protocol (stub)

Alongside `AGENTS.md`, Arqel exposes an **MCP server** (stub in Phase 1, full in Phase 2) that lets LLMs **explore the panel at runtime**:

```ts
// Phase 2 preview
import { ArqelMcpServer } from '@arqel-dev/mcp';

const server = new ArqelMcpServer({ panel: 'admin' });
// Tools: list-resources, get-resource-fields, list-actions, ...
```

Planned tools:

- `list-resources` — returns `[{ slug, label, model }]`
- `get-resource-fields(slug)` — schema of the Resource's fields
- `list-actions(slug)` — available actions
- `query-resource(slug, filters?, sort?, perPage?)` — preview of the index payload
- `inspect-policy(slug)` — Policy methods + their checks

Today the agent reads `AGENTS.md` + SKILL.md statically. In Phase 2, the MCP server enables dynamic queries — `"which fields does PostResource expose right now?"` returns the live schema via stdio JSON-RPC.

## Related links

- [agents.md](https://agents.md/) — community standard
- [`packages/core/stubs/agents.stub`](https://github.com/arqel-dev/arqel/blob/main/packages/core/stubs/agents.stub)
- [Roadmap Phase 2 — MCP](https://github.com/arqel-dev/arqel/blob/main/PLANNING/09-fase-2-essenciais.md)
- [Custom Fields](/advanced/custom-fields) — pattern LLMs use to generate custom field types

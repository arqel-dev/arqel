# AGENTS.md (LLM-friendly)

`AGENTS.md` es el estándar [agents.md](https://agents.md/) que provee contexto canónico a cualquier agente IA (Claude Code, Cursor, Windsurf, Aider, etc.) trabajando en un proyecto. Arqel **genera** uno automáticamente en `arqel:install`.

## Por qué importa

Los LLMs necesitan contexto explícito sobre stack, convenciones y dónde encontrar la verdad canónica. Sin `AGENTS.md`:

- El agente reinventa convenciones que ya fueron decididas
- Sugiere libs incompatibles (e.g. TanStack Query en un proyecto Inertia-only)
- Ignora los archivos SKILL.md de los paquetes
- Hace commits sin DCO, sin Conventional Commits, en inglés cuando debería ser PT-BR

Con `AGENTS.md`, el agente lo lee una vez al inicio de la sesión y se mantiene consistente.

## Lo que Arqel genera

`php artisan arqel:install` crea un `AGENTS.md` en la raíz del proyecto del usuario con 7 secciones:

### 1. Proyecto

```markdown
**Name:** Acme Admin
**Description:** Admin panel for system X
**Stack:** Laravel 12 + Inertia 3 + React 19 + Arqel
```

### 2. Stack

Lista las versiones mínimas (PHP 8.3+, Node 20.9+, etc.) y las libs principales. **Crítico:** menciona explícitamente que **Inertia es el único bridge PHP↔React permitido** ([ADR-001](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md)) — evitando que el agente sugiera TanStack Query.

### 3. Comandos frecuentes

```bash
composer install && pnpm install
php artisan serve
pnpm dev
vendor/bin/pest
pnpm test
vendor/bin/pint
pnpm lint
```

### 4. Convenciones obligatorias

- Idioma: inglés para código, PT-BR para docs/comunicación
- `declare(strict_types=1)` en cada archivo PHP
- Clases `final` por defecto
- Conventional Commits + DCO sign-off
- Tests-first (sin PR sin tests)

### 5. Estructura

```
app/
  Arqel/
    Resources/   # Resources de Arqel
    Widgets/     # Widgets del dashboard
  Models/        # Eloquent
  Policies/      # Policies de Laravel
resources/
  js/
    Pages/Arqel/   # Páginas Inertia (sobrescriben los defaults de Arqel)
    Arqel/Fields/  # Fields React personalizados
  css/app.css      # @import 'tailwindcss' + @arqel-dev/ui
```

### 6. Resumen de arquitectura

Resume los principales RF/RNF y apunta al `docs/` interno y a los SKILLs de los paquetes Arqel:

- `vendor/arqel-dev/core/SKILL.md`
- `vendor/arqel-dev/fields/SKILL.md`
- `vendor/arqel-dev/table/SKILL.md`
- ...

### 7. Enlaces

- Sitio de documentación de Arqel
- Source en GitHub
- ADRs canónicos
- Slack/Discord de la comunidad

## Cómo personalizar

`AGENTS.md` es tuyo — Arqel solo lo **inicializa**. Edítalo libremente:

- Añade contexto de dominio (vocabulario del negocio)
- Lista libs específicas de la app (e.g. spatie/laravel-permission)
- Documenta convenciones internas (e.g. "todo job es `ShouldQueue`")
- Apunta a playbooks internos

::: tip Versiónalo
Commitea `AGENTS.md` al repo — así cada agente que clone ve el mismo contexto. **No** lo añadas al `.gitignore`.
:::

## Plantilla completa

Puedes reproducir la plantilla cruda desde el source:

```bash
# En el source de Arqel
cat packages/core/stubs/agents.stub
```

O mira el propio `AGENTS.md` del monorepo de Arqel como referencia:

- [`AGENTS.md` en GitHub](https://github.com/arqel-dev/arqel/blob/main/AGENTS.md)

## MCP — Model Context Protocol (stub)

Junto con `AGENTS.md`, Arqel expone un **servidor MCP** (stub en Fase 1, completo en Fase 2) que permite a los LLMs **explorar el panel en runtime**:

```ts
// Preview Fase 2
import { ArqelMcpServer } from '@arqel-dev/mcp';

const server = new ArqelMcpServer({ panel: 'admin' });
// Tools: list-resources, get-resource-fields, list-actions, ...
```

Tools planificadas:

- `list-resources` — devuelve `[{ slug, label, model }]`
- `get-resource-fields(slug)` — schema de los fields del Resource
- `list-actions(slug)` — actions disponibles
- `query-resource(slug, filters?, sort?, perPage?)` — preview del payload del index
- `inspect-policy(slug)` — métodos de Policy + sus checks

Hoy el agente lee `AGENTS.md` + SKILL.md estáticamente. En Fase 2, el servidor MCP habilita queries dinámicas — `"qué fields expone PostResource ahora mismo?"` devuelve el schema en vivo vía stdio JSON-RPC.

## Enlaces relacionados

- [agents.md](https://agents.md/) — estándar de la comunidad
- [`packages/core/stubs/agents.stub`](https://github.com/arqel-dev/arqel/blob/main/packages/core/stubs/agents.stub)
- [Roadmap Fase 2 — MCP](https://github.com/arqel-dev/arqel/blob/main/PLANNING/09-fase-2-essenciais.md)
- [Custom Fields](/es/advanced/custom-fields) — patrón que los LLMs usan para generar tipos de Field personalizados

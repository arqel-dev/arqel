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

`php artisan arqel:install` crea un `AGENTS.md` en la raíz del proyecto del usuario con 5 secciones:

### 1. Project overview

Nombra la app, su versión de Arqel y el stack (PHP, Laravel, Inertia 3 + React 19 + Tailwind v4), y señala dónde viven los Resources y las páginas Inertia:

```markdown
Esta aplicação usa **Arqel** — admin panels declarativos em PHP, renderizados
em React via Inertia.

- Arqel Resources vivem em `app/Arqel/Resources/`
- Pages Inertia geradas em `resources/js/Pages/Arqel/`
```

### 2. Key conventions

- **Inertia-only:** nunca añadir TanStack Query, SWR, u otras fetch libs para el CRUD de Resources ([ADR-016](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md))
- Los Resources son la fuente de la verdad — la UI deriva de la definición en PHP
- Tests-first (Pest para PHP + Vitest para JS)
- Clases `final` por defecto
- `declare(strict_types=1)` en cada archivo PHP nuevo

### 3. Commands

```bash
# Scaffold de un nuevo Resource
php artisan arqel:resource <Model>

# Correr tests
vendor/bin/pest
npm run test

# Build / dev
npm run build
npm run dev
php artisan serve
```

### 4. Architecture

Un árbol del layout del proyecto — `app/Arqel/Resources` y `Widgets`, el `ArqelServiceProvider`, `config/arqel.php` y `resources/js/Pages/Arqel/`:

```
app/
├── Arqel/
│   ├── Resources/      ← Definiciones de Resource (CRUD declarativo)
│   └── Widgets/        ← Widgets del dashboard
config/
└── arqel.php           ← Path, guard, namespaces
resources/js/
└── Pages/Arqel/        ← Páginas Inertia (auto-resueltas)
```

### 5. Recursos adicionais

- Sitio de documentación de Arqel
- Issues en GitHub
- El SKILL.md de cada paquete Arqel en `vendor/arqel-dev/*/SKILL.md`

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

## MCP — Model Context Protocol

Junto con `AGENTS.md`, Arqel entrega un **servidor MCP** que permite a los LLMs explorar el framework y el panel. Corre vía stdio y se publica en npm como `@arqel-dev/mcp-server`:

```jsonc
// .mcp.json / config de Claude Desktop
{
  "mcpServers": {
    "arqel": {
      "command": "npx",
      "args": ["-y", "@arqel-dev/mcp-server"]
    }
  }
}
```

Expone 7 tools:

- `search_docs` — búsqueda full-text en la documentación
- `get_adr` — obtiene un ADR canónico por número
- `get_api_reference` — consulta un símbolo de la API PHP/React
- `list_resources` — lista los Resources registrados en el proyecto
- `describe_resource` — fields, table y form schema de un Resource
- `generate_resource` — genera el scaffold de un nuevo Resource
- `generate_field` — genera el scaffold de un field personalizado

El lado PHP (composer `arqel-dev/mcp`) implementa el core JSON-RPC del `McpServer` más los registries de tool/resource/prompt. El único follow-up pendiente es el comando Artisan `arqel:mcp:serve`; hasta que llegue, los integradores pueden llamar a `McpServer::serve()` desde un script personalizado. Mira la [guía del servidor MCP](/es/guide/mcp-server) para el setup completo.

## Enlaces relacionados

- [agents.md](https://agents.md/) — estándar de la comunidad
- [`packages/core/stubs/agents.stub`](https://github.com/arqel-dev/arqel/blob/main/packages/core/stubs/agents.stub)
- [Roadmap Fase 2 — MCP](https://github.com/arqel-dev/arqel/blob/main/PLANNING/09-fase-2-essenciais.md)
- [Custom Fields](/es/advanced/custom-fields) — patrón que los LLMs usan para generar tipos de Field personalizados

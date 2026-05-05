# Servidor MCP

> Paquete: [`arqel-dev/mcp`](../../packages/mcp/) · Tickets: MCP-001..010

## Propósito

`arqel-dev/mcp` expone un servidor [Model Context Protocol](https://modelcontextprotocol.io) sobre los panels de Arqel: **tools** (ejecutar Actions, mutar Resources), **resources** (leer datos de Resource/Table/Widget), **prompts** (templates pre-construidos para flujos comunes de admin).

Permite que clientes MCP — Claude Desktop, Cursor, Zed, agentes custom — manejen el panel vía JSON-RPC con la misma autorización y validación que ven los usuarios humanos en la UI Inertia.

La elección es **adherirse a la spec del protocolo**: sin desviaciones de `modelcontextprotocol.io`; el paquete es solo la traducción PHP/Laravel de las primitivas (Tool, Resource, Prompt) y el glue que descubre Resources/Actions ya registrados en `arqel-dev/core`.

## Setup — Claude Desktop

Edita la config:

- macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
- Windows: `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "arqel": {
      "command": "php",
      "args": ["artisan", "arqel:mcp:serve"],
      "cwd": "/path/to/your/laravel/app"
    }
  }
}
```

> **Nota:** el comando Artisan `arqel:mcp:serve` aún está diferido — llega en un ticket de seguimiento que involucra `McpServer::serve()`. Por ahora, los integradores pueden instanciar `McpServer` en un script PHP custom y llamar `serve()` directamente:
>
> ```php
> $server = app(Arqel\Mcp\McpServer::class);
> $server->serveStreams(STDIN, STDOUT);
> ```

Las guías de setup para Cursor y Windsurf se añadirán cuando el comando Artisan se estabilice.

## Tools incluidas

Cuatro tools auto-registradas en `McpServer` vía `packageBooted`:

### `list_resources`

Lista todos los Resources de `Arqel\Core\Resources\ResourceRegistry`. Payload por entrada: `{class, model, slug, label, pluralLabel, navigationGroup}`. Un Resource roto en metadatos se ignora silenciosamente.

### `describe_resource`

Input: `{slug: string (required)}`. Devuelve 8 claves estáticas (`class/slug/model/label/pluralLabel/navigationIcon/navigationGroup/navigationSort`). Slug desconocido → `RuntimeException` (`-32602`).

### `generate_resource`

Wrapper para el comando Artisan `arqel:resource`. Input: `{model: string, fromModel: bool, withPolicy: bool}`. Closure runner inyectable; el default delega a `Kernel::call`.

### `run_test`

Wrapper Pest/PHPUnit para TDD-loop. Input: `{filter, path, coverage, timeout}` — todos opcionales. **Guard de path traversal**: rechaza `..` y paths absolutos con `InvalidArgumentException`. Timeout clamp a `[1, 600]` segundos (default 300).

## Resources incluidos

### `arqel-skill://<package>`

`SkillResource` descubre `packages/*/SKILL.md` en el monorepo. El esquema URI valida la regex `[a-z0-9-]+`. `list()` + `read(uri)`; uri inválido → `RuntimeException`. Closure de reader inyectable para tests.

Útil para que un cliente MCP introspeccione **la documentación canónica** de cada paquete sin leer tickets dispersos en `PLANNING/`.

## Prompts incluidos

### `migrate_filament_resource`

Template que inserta el contenido de un archivo Filament PHP (Resource o Page) dentro de un fenced code block + guidelines de migración para Arqel. Argumento obligatorio: `filament_file`.

### `review_resource`

Template para code-review de un Resource Arqel. Argumento obligatorio: `resource_file`. Inserta el source + checklist (Policy, FormRequest, tipos de Field, autorización de Action).

Ambos tienen un **guard de path traversal** (`..` rechazado antes del reader).

## Envelope JSON-RPC

Adhiere a la spec MCP `2024-11-05`. Códigos de error:

| Código | Cuándo |
|---|---|
| `-32600` | Envelope inválido |
| `-32601` | Method no encontrado |
| `-32602` | Params inválidos / lookup falló |
| `-32603` | Throw del handler |
| `-32700` | Parse error (JSON malformado) |

Notifications (sin `id`) → respuesta vacía `[]`. El wrapping del result es automático:

- Tool → `{content: [{type: 'text', text}]}`
- Resource → `{contents: [{uri, text, mimeType?}]}`
- Prompt → `{description, messages}`

## Consideraciones de seguridad

- **Auth**: las sesiones MCP son JSON-RPC long-running, no requests Inertia — el middleware HTTP de Laravel **no** se aplica. Auth necesita su propio pipeline (token-bound, sin CSRF). La implementación canónica llega en un ticket futuro.
- **Exposición de Resources**: opt-in explícito. Modelos sensibles (User, Tenant billing) **nunca** deben ser legibles por defecto. Recomendación: una flag de config + un método `Resource::exposeToMcp(): bool` para exposición granular.
- **Path traversal**: `RunTestTool` y prompts rechazan `..` y paths absolutos antes de tocar el filesystem. No bypassees.
- **Spec-first**: sin extensiones custom más allá de la spec. Cuando la spec evoluciona, este paquete sigue — no extiende. Si necesitas algo, abre un PR upstream en `modelcontextprotocol.io`.

## Anti-patrones

- ❌ Inventar tools/resources fuera de la spec MCP.
- ❌ Reusar middleware HTTP de Laravel para auth de sesión MCP.
- ❌ Exponer cada Resource del panel automáticamente.
- ❌ Bypassear el guard de path traversal en tools que tocan el filesystem.

## Relacionado

- [`packages/mcp/SKILL.md`](../../packages/mcp/SKILL.md)
- [`PLANNING/09-fase-2-essenciais.md`](../../PLANNING/09-fase-2-essenciais.md) §MCP-001..010
- [Spec del Model Context Protocol](https://modelcontextprotocol.io)

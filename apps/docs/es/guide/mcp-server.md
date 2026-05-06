# Servidor MCP

> Paquete: [`@arqel-dev/mcp-server`](https://www.npmjs.com/package/@arqel-dev/mcp-server) · Roadmap: [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)

El **servidor MCP de Arqel** es el servidor oficial [Model Context Protocol](https://modelcontextprotocol.io) del framework. Distribuido como paquete npm con el binario `arqel-mcp` ejecutado por stdio, da a los asistentes de IA — Claude Code, Cursor, Copilot CLI, Gemini CLI — acceso directo a la documentación de Arqel, a los ADRs, a la API reference (PHP + TypeScript), a la introspección de proyectos Laravel que usan Arqel y a scaffolding consistente con las convenciones del proyecto.

## Por qué usarlo

- **Documentación siempre actualizada** — el tarball publicado embebe una copia de `apps/docs/`, los ADRs y la API reference, así el asistente nunca cita una versión desactualizada obtenida del training data.
- **Introspección consciente del proyecto** — las tools de introspección descubren Resources reales registrados en el proyecto Laravel del usuario llamando a `php artisan arqel:introspect --json`.
- **Scaffolding consistente** — la generación de Resources y Fields usa los mismos stubs canónicos de `arqel-dev/core`, evitando que el asistente "improvise" código fuera de las convenciones.

## Instalación

### Claude Code

```bash
claude mcp add arqel npx -- -y @arqel-dev/mcp-server
```

Esto registra el servidor en la lista de MCPs de Claude Code. La primera invocación hace que `npx` descargue el paquete; las llamadas siguientes usan el caché.

### Cursor

Edita (o crea) `.cursor/mcp.json` en la raíz del proyecto:

```json
{
  "mcpServers": {
    "arqel": {
      "command": "npx",
      "args": ["-y", "@arqel-dev/mcp-server"]
    }
  }
}
```

Reinicia Cursor. El servidor aparece en la lista de MCPs activos.

### Copilot CLI / Gemini CLI

Los formatos de configuración de MCP varían según el cliente y aún evolucionan rápido. Consulta la documentación de MCP de tu cliente — la forma canónica siempre es una entrada `command: "npx"` + `args: ["-y", "@arqel-dev/mcp-server"]`.

## Tools disponibles

El servidor expone **7 tools** (los próximos añadidos quedarán registrados en [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)):

| Tool | Descripción |
|---|---|
| `search_docs(query, limit?)` | Busca el corpus de documentación (BM25 sobre `apps/docs/`). |
| `get_adr(id)` | Devuelve el contenido completo de un ADR (`ADR-001` … `ADR-018`). |
| `get_api_reference(symbol, language?)` | Devuelve la reference oficial de un símbolo PHP o TypeScript. |
| `list_resources(projectPath?)` | Lista Resources Arqel registrados en el proyecto Laravel del usuario. |
| `describe_resource(class, projectPath?)` | Devuelve metadata estructurada de un Resource concreto (model, fields, navigation). |
| `generate_resource(model, fields[], …)` | Genera el archivo `<Model>Resource.php` con el stub canónico. |
| `generate_field(name, type, options?)` | Genera la línea de declaración de un Field (e.g., `Text::make('title')->required()`). |

Ejemplo de llamada (formato JSON-RPC, ejecutado internamente por el cliente MCP):

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "search_docs",
    "arguments": { "query": "field types", "limit": 5 }
  }
}
```

## Resolución del proyecto Laravel

Las tools de introspección (`list_resources`, `describe_resource`) necesitan saber qué proyecto Laravel inspeccionar. La resolución sigue este orden:

1. **Argumento `projectPath`** pasado en la llamada de la tool (precedencia máxima).
2. **Variable de entorno `ARQEL_PROJECT_PATH`** definida en el entorno del servidor MCP.
3. **Walk-up automático** desde el `cwd` del servidor — sube directorios hasta encontrar un `artisan`.

::: tip Binario PHP personalizado
Si tu `php` no está en `$PATH` (Herd, Valet, Docker, proyectos con PHP versionado), define `ARQEL_PHP_BIN` apuntando al binario correcto. Ejemplo:

```bash
export ARQEL_PHP_BIN=/Users/me/Library/Application\ Support/Herd/bin/php
```
:::

## Repositorio fuente

- Source: [`packages-js/mcp-server/`](https://github.com/arqel-dev/arqel/tree/main/packages-js/mcp-server)
- Roadmap post-MVP: [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)
- Spec MCP: [modelcontextprotocol.io](https://modelcontextprotocol.io)

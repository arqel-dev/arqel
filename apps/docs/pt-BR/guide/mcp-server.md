# MCP server

> Pacote: [`@arqel-dev/mcp-server`](https://www.npmjs.com/package/@arqel-dev/mcp-server) · Roadmap: [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)

O **Arqel MCP server** é o servidor [Model Context Protocol](https://modelcontextprotocol.io) oficial do framework. Distribuído como pacote npm com binário `arqel-mcp` executado por stdio, dá a assistentes de IA — Claude Code, Cursor, Copilot CLI, Gemini CLI — acesso direto à documentação do Arqel, aos ADRs, à API reference (PHP + TypeScript), à introspecção de projetos Laravel que usam Arqel e a scaffolding consistente com as convenções do projeto.

## Por que usar

- **Documentação sempre atualizada** — o tarball publicado embute uma cópia de `apps/docs/`, dos ADRs e da API reference, então o assistente nunca cita uma versão antiga obtida via training data.
- **Project-aware introspection** — as tools de introspecção descobrem Resources reais registados no projeto Laravel do utilizador chamando `php artisan arqel:introspect --json`.
- **Scaffolding consistente** — a geração de Resources e Fields usa os mesmos stubs canónicos do `arqel-dev/core`, evitando que o assistente "improvise" código fora das convenções.

## Instalação

### Claude Code

```bash
claude mcp add arqel npx -- -y @arqel-dev/mcp-server
```

Isto regista o servidor na lista de MCPs do Claude Code. A primeira invocação faz `npx` baixar o pacote; chamadas seguintes usam o cache.

### Cursor

Edita (ou cria) `.cursor/mcp.json` na raiz do projeto:

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

Reinicia o Cursor. O servidor aparece na lista de MCPs ativos.

### Copilot CLI / Gemini CLI

Os formatos de configuração de MCP variam por cliente e ainda evoluem rapidamente. Consulta a documentação de MCP do teu cliente — a forma canónica é sempre uma entrada `command: "npx"` + `args: ["-y", "@arqel-dev/mcp-server"]`.

## Tools disponíveis

O servidor expõe **7 tools** (acréscimos serão anunciados em [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)):

| Tool | Descrição |
|---|---|
| `search_docs(query, limit?)` | Pesquisa o corpus de documentação (BM25 sobre `apps/docs/`). |
| `get_adr(id)` | Devolve o conteúdo completo de um ADR (`ADR-001` … `ADR-018`). |
| `get_api_reference(symbol, language?)` | Devolve a reference oficial para um símbolo PHP ou TypeScript. |
| `list_resources(projectPath?)` | Lista Resources Arqel registados no projeto Laravel do utilizador. |
| `describe_resource(class, projectPath?)` | Devolve metadata estruturada de um Resource específico (model, fields, navigation). |
| `generate_resource(model, fields[], …)` | Gera o ficheiro `<Model>Resource.php` com o stub canónico. |
| `generate_field(name, type, options?)` | Gera a linha de declaração de um Field (e.g., `Text::make('title')->required()`). |

Exemplo de chamada (formato JSON-RPC, internamente executado pelo cliente MCP):

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

## Resolução do projeto Laravel

As tools de introspecção (`list_resources`, `describe_resource`) precisam de saber qual é o projeto Laravel a inspecionar. A resolução segue esta ordem:

1. **Argumento `projectPath`** passado na chamada da tool (precedência máxima).
2. **Variável de ambiente `ARQEL_PROJECT_PATH`** definida no ambiente do servidor MCP.
3. **Walk-up automático** a partir do `cwd` do servidor — sobe diretórios até encontrar um `artisan`.

::: tip Binário PHP customizado
Se o teu `php` não está em `$PATH` (Herd, Valet, Docker, projetos com PHP versionado), define `ARQEL_PHP_BIN` apontando ao binário correto. Exemplo:

```bash
export ARQEL_PHP_BIN=/Users/me/Library/Application\ Support/Herd/bin/php
```
:::

## Repositório fonte

- Source: [`packages-js/mcp-server/`](https://github.com/arqel-dev/arqel/tree/main/packages-js/mcp-server)
- Roadmap pós-MVP: [`PLANNING/13-pos-mvp-mcp-server.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/13-pos-mvp-mcp-server.md)
- Spec MCP: [modelcontextprotocol.io](https://modelcontextprotocol.io)

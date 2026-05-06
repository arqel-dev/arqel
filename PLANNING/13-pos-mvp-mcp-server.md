# Pós-MVP — MCP Server para Arqel

> **Status:** Iniciativa pós-MVP (Fase 1 concluída em v0.8.x).
> **Objetivo:** Entregar um servidor MCP (Model Context Protocol) oficial do Arqel que ajude desenvolvedores que usam assistentes de IA (Claude Code, Cursor, Copilot CLI, etc.) a construir admin panels com o framework, e ajude também contribuidores do próprio framework.

## 1. Contexto

O Arqel posiciona-se como alternativa open-source ao Filament/Nova com foco em DX. Um diferencial crítico em 2026 é a integração nativa com assistentes de IA — devs cada vez mais escrevem código com Claude/Cursor, e a qualidade do output depende de quão bem a IA "entende" o framework.

Sem MCP server, o assistente cai em três falhas previsíveis:

1. **Docs desatualizadas** — modelo usa snapshot de treino, não conhece APIs publicadas após o cutoff.
2. **Sem visão do projeto** — não sabe que Resources/Panels existem na app específica do utilizador.
3. **Scaffolding inconsistente** — gera código que parece correto mas viola convenções (estrutura de Resource, naming, etc.).

Um MCP oficial resolve os três: docs sempre atuais, introspection do projeto Laravel, scaffolding por stubs canônicos.

## 2. Objetivos

- **Público duplo:** dev consumidor do framework + contribuidor do monorepo Arqel.
- **7 tools no MVP** distribuídas em 3 categorias: 3 docs (`search_docs`, `get_adr`, `get_api_reference`), 2 introspection (`list_resources`, `describe_resource`), 2 scaffolding (`generate_resource`, `generate_field`).
- **Distribuição via npm stdio** (`@arqel-dev/mcp-server`), instalável com `claude mcp add` e equivalentes.
- **Servidor HTTP hospedado** fica fora do escopo desta iniciativa (avaliar depois conforme demanda).

## 3. Arquitetura

```
┌────────────────────────┐
│ Claude Code / Cursor   │
└──────────┬─────────────┘
           │ stdio
┌──────────▼─────────────┐
│ @arqel-dev/mcp-server  │  TypeScript, @modelcontextprotocol/sdk
│  (packages-js/mcp-…)   │
└──────────┬─────────────┘
           │ subprocess
┌──────────▼─────────────┐
│ php artisan            │  arqel:introspect (no core)
│   arqel:introspect     │  retorna JSON
└────────────────────────┘
```

**Decisões fundadoras:**
- TypeScript no servidor MCP (alinhado com `packages-js/`, ecossistema MCP é primariamente TS).
- PHP fica responsável pela introspection do projeto Laravel via Artisan command público (`arqel:introspect --json`). O MCP server invoca-o como subprocess. Isto evita reimplementar lógica do core em TS e expõe um comando útil também fora do MCP.
- Docs e ADRs são lidos diretamente do filesystem do monorepo (quando o MCP corre dentro do repo) ou de cópias empacotadas no npm bundle (quando corre na app do utilizador).

**Não-objetivos:**
- Não substitui Context7 (que cobre bibliotecas de terceiros).
- Não executa código arbitrário no projeto-alvo — só comandos Artisan documentados.
- Não tem servidor HTTP nesta iniciativa.

## 4. Tickets

### [MCP-001] Setup do pacote `@arqel-dev/mcp-server`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** shared • **Depende de:** —

**Contexto:** criar o esqueleto do pacote npm que hospedará o servidor MCP.

**Descrição técnica:**
- Criar `packages-js/mcp-server/` no monorepo.
- `package.json` com `bin: { "arqel-mcp": "./dist/index.js" }`, `type: module`, declarado no pnpm workspace.
- Dependência `@modelcontextprotocol/sdk` (versão estável corrente).
- Build com tsup (alinhado aos outros pacotes JS), saída ESM single-file.
- Entrypoint `src/index.ts` com `Server` MCP iniciado via stdio transport, ainda sem tools registadas.
- Smoke test com Vitest validando que servidor inicia e responde ao handshake `initialize`.

**Critérios de aceite:**
- [ ] Pacote builda (`pnpm --filter @arqel-dev/mcp-server build`)
- [ ] Smoke test passa
- [ ] Binário `arqel-mcp` é executável após build
- [ ] `SKILL.md` na raiz do pacote

### [MCP-002] Artisan command `arqel:introspect`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** php • **Depende de:** —

**Contexto:** o MCP server precisa duma fonte canônica de dados sobre Resources/Panels/Fields registados na app. Em vez de o TS reimplementar leitura do PanelRegistry, expomos um Artisan command que serializa o estado para JSON.

**Descrição técnica:**
- Criar `IntrospectCommand` em `packages/core/src/Console/`.
- Signature: `arqel:introspect {--json} {--scope=all : panels|resources|fields|all}`.
- Output JSON estruturado: `{ panels: [...], resources: [...], fields: [...], version: "x.y.z" }`.
- Cada Resource inclui: classe, model, label, fields (nome+tipo), URL slug, polices aplicáveis.
- Registar o command no `CoreServiceProvider`.
- Testes Pest cobrindo: panel vazio, panel com 1 Resource, formato JSON estável.

**Critérios de aceite:**
- [ ] `php artisan arqel:introspect --json` retorna JSON válido
- [ ] Coverage ≥90% no command
- [ ] Documentado em `packages/core/SKILL.md`

### [MCP-003] Tool `search_docs`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** S • **Camada:** shared • **Depende de:** MCP-001

**Contexto:** permitir que IA pesquise nas docs oficiais (`apps/docs/`) por termos, retornando trechos relevantes com path do arquivo.

**Descrição técnica:**
- Implementar tool MCP `search_docs(query: string, limit?: number)`.
- Indexação simples in-memory na inicialização: walk recursivo em `apps/docs/`, ler `.md`, dividir por heading.
- Search com matching case-insensitive + score por contagem de ocorrências (sem dependência externa para MVP; lunr/minisearch fica como follow-up).
- Quando MCP corre fora do monorepo (instalado pelo utilizador), ler docs duma cópia empacotada no npm tarball.

**Critérios de aceite:**
- [ ] Tool retorna ≤10 resultados com `{ path, heading, excerpt }`
- [ ] Funciona dentro e fora do monorepo
- [ ] Testes Vitest

### [MCP-004] Tool `get_adr`

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** S • **Camada:** shared • **Depende de:** MCP-001

**Contexto:** ADRs são canônicos no projeto. IA precisa consultá-los para não contradizer decisões.

**Descrição técnica:**
- Tool `get_adr(id: string)` — `id` é o número do ADR (e.g., `001`, `016`).
- Lê `PLANNING/03-adrs.md` (cópia empacotada no npm tarball quando fora do monorepo).
- Parse simples por heading `## ADR-XXX`.
- Retorna `{ id, title, status, body }`.

**Critérios de aceite:**
- [ ] Resolve todos 18 ADRs
- [ ] Retorna erro estruturado para ID inexistente
- [ ] Testes Vitest

### [MCP-005] Tool `get_api_reference`

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** M • **Camada:** shared • **Depende de:** MCP-001

**Contexto:** APIs PHP e React estão documentadas em `PLANNING/05-api-php.md` e `PLANNING/06-api-react.md`. Tool dá acesso direto.

**Descrição técnica:**
- Tool `get_api_reference(symbol: string, language?: 'php'|'react')`.
- Indexa headings dos dois ficheiros na inicialização.
- Match por nome exato primeiro, fallback fuzzy.
- Retorna `{ symbol, language, signature, description, examples }`.

**Critérios de aceite:**
- [ ] Resolve `Resource`, `Field`, `Panel`, `useResource`, etc.
- [ ] Filtro por language funciona
- [ ] Testes Vitest

### [MCP-006] Tools `list_resources` + `describe_resource`

**Tipo:** feat • **Prioridade:** P1 • **Estimativa:** M • **Camada:** shared • **Depende de:** MCP-001, MCP-002

**Contexto:** introspection do projeto Laravel do utilizador via subprocess Artisan.

**Descrição técnica:**
- Tool `list_resources()` — invoca `php artisan arqel:introspect --json --scope=resources`, retorna lista.
- Tool `describe_resource(class: string)` — filtra resultado por FQCN.
- Auto-detect do projeto: procura `artisan` na CWD do MCP server e em pais até root.
- Erro estruturado se não encontrar projeto Laravel ou se Artisan falhar.

**Critérios de aceite:**
- [ ] Funciona em `apps/demo/`
- [ ] Erro claro quando fora dum projeto Laravel
- [ ] Testes Vitest com mock de subprocess

### [MCP-007] Tools `generate_resource` + `generate_field`

**Tipo:** feat • **Prioridade:** P2 • **Estimativa:** L • **Camada:** shared • **Depende de:** MCP-001

**Contexto:** scaffolding consistente com convenções do framework.

**Descrição técnica:**
- Tool `generate_resource(model: string, fields: Array<{name, type}>)` — produz código PHP do Resource via stubs canônicos (sem escrever no disco; retorna o código para o assistente decidir o que fazer).
- Tool `generate_field(name: string, type: string, options?: object)` — produz snippet PHP para um Field.
- Stubs vivem em `packages-js/mcp-server/stubs/` e ficam alinhados aos templates do `arqel:make:resource` (CLI já existente no core).
- Validação de inputs com Zod.

**Critérios de aceite:**
- [ ] Output é PHP válido (parse com `php -l` em teste)
- [ ] Cobre todos field types do MVP
- [ ] Testes Vitest com snapshots

### [MCP-008] Docs + publicação npm

**Tipo:** docs • **Prioridade:** P1 • **Estimativa:** M • **Camada:** docs • **Depende de:** MCP-001..MCP-007

**Contexto:** sem instruções claras de instalação, o pacote não é adotado.

**Descrição técnica:**
- Página `apps/docs/.../guide/mcp-server.md` cobrindo: o que é, instalação em Claude Code/Cursor/Copilot CLI, lista de tools com exemplos.
- Atualizar `apps/docs/.../guide/getting-started.md` com referência ao MCP server.
- Adicionar entry no `release.yml` para publicar `@arqel-dev/mcp-server` junto dos outros pacotes.
- Smoke E2E: instalar tarball localmente, registar em Claude Code, verificar handshake.

**Critérios de aceite:**
- [ ] Pacote publicado em npm com tag inicial (`v0.9.0` ou seguinte)
- [ ] Docs em pelo menos PT-BR (EN/ES como follow-up)
- [ ] Instruções `claude mcp add` testadas end-to-end

## 5. Critérios de saída da iniciativa

- 8 tickets concluídos com testes e coverage acima do threshold definido em `12-processos-qa.md`.
- Pacote `@arqel-dev/mcp-server` publicado em npm com pelo menos uma versão estável.
- Página de documentação publicada em `arqel.dev`.
- Smoke E2E demonstrando o servidor a responder via Claude Code num projeto Laravel real (`apps/demo/`).

### Nota de coordenação de release

O job `publish-npm` em `.github/workflows/release.yml` itera todos pacotes `packages-js/*` não-private e exige que cada `package.json` tenha `version` igual à tag git (`v<version>`). Como o `@arqel-dev/mcp-server` arranca com `0.9.0` e os outros pacotes JS estão actualmente em `0.8.x`, qualquer corte de tag `v0.9.0` para publicar este pacote requer **uma das três opções**:

1. **Bump coordenado** — bumpar todos os pacotes `packages-js/*` para `0.9.0` antes da tag (alinhamento total de versões).
2. **Carve-out no workflow** — adicionar um `skip-list` ao step de verificação que excepciona pacotes com versão diferente da tag.
3. **Tag dedicada** — usar uma tag separada para o mcp-server (e.g., `mcp-server-v0.9.0`) com um job dedicado que só publica este pacote.

Opção (1) é a mais simples se o framework estiver a aproximar-se duma release coordenada de qualquer forma; (2) é flexível mas adiciona complexidade ao workflow; (3) é mais limpa a longo prazo se o ciclo de release do MCP server for genuinamente independente. Decisão fica para o momento da publicação.

## 6. Fora de escopo (follow-ups)

- Servidor HTTP hospedado em `mcp.arqel.dev`.
- Tools adicionais: `generate_form`, `generate_action`, `generate_widget`, `generate_panel`.
- Auto-aplicação de scaffolding no disco (atualmente só retorna código).
- Indexação full-text mais sofisticada (lunr/minisearch).
- Telemetria opcional de uso das tools.

# Design — External validation project (`arqel-test`) + MCP integration

**Data:** 2026-05-06
**Estado:** Aprovado pelo utilizador (aguarda review do spec escrito antes da implementação)
**Autor:** Claude Opus 4.7 (1M context) com review de Diogo C. Coutinho
**Spec antecessor:** [`2026-05-06-fresh-laravel-e2e-validation-design.md`](./2026-05-06-fresh-laravel-e2e-validation-design.md)

---

## Contexto

O spec antecessor recriou `apps/demo/` no monorepo via path repos para validar `arqel:install` e o ciclo de Resource CRUD ponta-a-ponta. Esse trabalho gerou 10 commits de fix na branch `feat/e2e-validation` cobrindo bugs reais descobertos no walkthrough manual:

- `effa873` — filtros do `TableQueryBuilder` esperam shape `?filter[name]=value`
- `43e43e9` — `ArqelIndexPage` não estava ligado ao router Inertia
- `245cd8f` — rotas de auth sem `HandleArqelInertiaRequests`
- `e7801ec` — `deriveColumnsFromFields` emitindo shape de `ColumnBase` incompleto (faltavam `props`, `copyable`, etc.)
- `32ac09c` — `deriveColumnsFromFields` não suportava fields em formato array
- `7bc8238` — `SelectFilter` serializando options como assoc-array (frontend espera `[{value,label}]`)
- `bd31e16` — smoke test do welcome blade quebrado pelo install
- `b211ff0` — `PostResource` showcase
- `4ca7c2a` — recriação completa do `apps/demo/`
- `5233d6e` — finalização do rename `arqel-dev/arqel` → `arqel-dev/framework`

Entre o início do walkthrough e agora, a branch `main` avançou (v0.9.0 publicada, MCP server implementado e publicado no NPM como `@arqel-dev/mcp-server@0.9.0`, fixes de release CI). Os fixes do walkthrough estão isolados em `feat/e2e-validation` e ainda não chegaram à `main`.

**Próximo passo natural** é validar que tudo funciona quando o consumidor instala via Packagist real (não path repos) num Laravel limpo fora do monorepo, e simultaneamente exercitar o MCP server publicado para confirmar que assistentes IA (Claude Code, etc.) conseguem operar sobre o projeto via tools `list_resources`, `describe_resource`, `generate_resource`, etc.

## Estado actual verificado

- **Packagist:** `arqel-dev/framework` v0.8.0, v0.8.1, v0.9.0 publicados
- **NPM:** `@arqel-dev/mcp-server@0.9.0` publicado (audiência: assistentes IA construindo apps Arqel)
- **Branch local `feat/e2e-validation`:** HEAD `effa873`, 10 commits à frente da `main` (`9177f34`)
- **`~/PhpstormProjects/arqel-test/`:** não existe ainda
- **Tasks pendentes do spec antecessor:** 11 (walkthrough manual — *de facto* concluído pela validação dos 10 commits de fix), 12 (cleanup `demo-old`), 13 (push), 14 (criar `arqel-test`), 15 (tag condicional)

## Objectivos

1. **Garantir que os fixes de `feat/e2e-validation` chegam à main** e ficam disponíveis num release público (v0.9.1).
2. **Validar instalação via Packagist real** num projeto Laravel limpo fora do monorepo, sem path repos, sem symlinks, sem `@dev`.
3. **Cobertura máxima de features Fase 1 MVP** num único projeto consumidor.
4. **Validar integração MCP** ponta-a-ponta — Claude Code consegue invocar tools do `@arqel-dev/mcp-server` apontando para o projeto externo.
5. **Produzir relatório auditável** que sirva de gate para abrir Fase 2 do roadmap.

## Não-objetivos

- Não implementar features Fase 2/3/4 do roadmap.
- Não publicar `arqel-test` no GitHub (sandbox local permanente).
- Não escrever suite Playwright E2E (fica para sprint dedicada futura).
- Não cobrir PostgreSQL/MySQL — SQLite chega para validação Fase 1.
- Não construir/modificar o MCP server (já existe e está publicado).

---

## Arquitectura — cinco fases sequenciais

```
Fase 0 — Release pipeline (rebase + tag v0.9.1)
  └─→ Fase 1 — Bootstrap arqel-test via Packagist
       └─→ Fase 2 — Cobertura de recursos (Post + Category + User + nav + widget)
            └─→ Fase 3 — Integração MCP
                 └─→ Fase 4 — Validação e relatório
                      └─→ Fase 5 — Cleanup do monorepo
```

Cada fase termina com critérios verificáveis. Falhas na Fase 0 abortam tudo (sem v0.9.1 não há o que testar). Falhas em Fases 1-3 produzem hotfix → bump → repetir Fase 0. Falhas em Fase 4 viram tickets no `PLANNING/`.

```
[monorepo arqel] ──tag v0.9.1──> [Packagist + NPM]
                                          │
                                          ▼
                          [~/PhpstormProjects/arqel-test/]
                              ├── composer require arqel-dev/framework:^0.9.1
                              ├── npx -y @arqel-dev/mcp-server@0.9.1 (MCP)
                              └── php artisan serve → walkthrough manual
                                          │
                                          ▼
                  [docs/superpowers/reports/2026-05-06-...md no monorepo]
```

---

## Fase 0 — Release pipeline

### Objectivo

Levar os 10 commits de fix da `feat/e2e-validation` para a `main` e publicar v0.9.1 em Packagist + NPM.

### Passos

1. Garantir que `feat/e2e-validation` está limpa (working tree, sem untracked críticos).
2. `git fetch origin && git rebase origin/main` na branch.
3. Resolver conflitos. Esperados:
   - `composer.lock` em `apps/demo/` (regenerar)
   - `package.json` versions em todos os pacotes (main já bumpou para 0.9.0; rebase precisa manter 0.9.0 com os fixes em cima)
   - Possivelmente `apps/demo/` (fixes de release tocaram em config)
4. Push fast-forward `feat/e2e-validation` → `main` directamente (não há PR review obrigatório no monorepo conforme política actual; perguntar ao user antes de push se há mudança de política).
5. Bump versão `0.9.0 → 0.9.1` em todos os pacotes (mesmo script/cadência do bump anterior — provavelmente `pnpm changeset` ou script custom).
6. Tag `v0.9.1` + `git push --tags`.
7. CI corre subtree split → publica `arqel-dev/{framework,core,auth,fields,form,actions,nav,table}@v0.9.1` no Packagist + `@arqel-dev/{ui,types,mcp-server,...}@0.9.1` no NPM.
8. Verificar resolução real:
   - `composer show arqel-dev/framework --available` deve listar 0.9.1
   - `npm view @arqel-dev/mcp-server@0.9.1` deve retornar metadata válido

### Critério de saída

v0.9.1 resolvable em ambos registries via comandos públicos (sem auth, sem path repos).

### Saída de emergência

Se CI falhar, **parar tudo**, debugar (provavelmente algo em `.github/workflows/release.yml` ou no script de subtree split), abrir hotfix → repetir Fase 0. Não avançar para Fase 1 sem v0.9.1 publicada.

---

## Fase 1 — Bootstrap do projeto externo

### Objectivo

Criar `~/PhpstormProjects/arqel-test/` como projeto Laravel 13 limpo consumindo `arqel-dev/framework:^0.9.1` exclusivamente via Packagist público.

### Passos

1. `cd ~/PhpstormProjects && laravel new arqel-test` (Laravel 13 default, sem starter kit, sem Jetstream).
2. `cd arqel-test && git init && git add -A && git commit -m "chore: laravel skeleton baseline"` (baseline de comparação).
3. `composer require arqel-dev/framework:^0.9.1` — sem path repos, sem `@dev`, sem flags exóticas. Exactamente como aparece no README.
4. `php artisan arqel:install` — registra middleware, rotas, panel; copia stubs React; corre `shadcn init`; instala Inertia + Vite + Tailwind via `pnpm add`.
5. `php artisan migrate` (SQLite default em `database/database.sqlite`).
6. `php artisan arqel:make-user` — cria admin de seed (interactive prompt ou flags).
7. `pnpm install && pnpm run build`.
8. `php artisan arqel:doctor` — verifica install end-to-end.
9. Commit: `chore: install arqel-dev/framework v0.9.1`.

### Critério de saída

`php artisan serve` + login em `/admin/login` + landing `/admin` renderizam sem erro de console no browser. Smoke pass.

### Saída de emergência

Qualquer erro no `arqel:install` ou no build → parar, abrir ticket, hotfix → v0.9.2 → repetir Fase 0+1.

---

## Fase 2 — Cobertura de recursos

### Objectivo

Implementar 3 resources que juntos exercitam praticamente todo o catálogo de features Fase 1 MVP.

### Resources

#### `PostResource`

Replicar do `apps/demo/` (já validado no walkthrough interno). Cobre:

- Fields: `TextField`, `TextareaField`, `SelectField`, `ToggleField`, `DateTimeField`, `HiddenField`
- Validation rules (required, max, in, etc.)
- Filters: `SelectFilter` (status), `TernaryFilter` (featured)
- Search global (title)
- Sort por coluna
- Paginação
- Actions: create, edit, delete, bulk delete
- Auth: `user_id` automático via `HiddenField::default(fn () => auth()->id())`

#### `CategoryResource`

Novo. Cobre o que Post não cobre:

- Relationship: `Post belongsTo Category` (precisa migration `add_category_id_to_posts`)
- `SelectField::optionsRelationship('category', 'name')` no form
- `SelectFilter::optionsRelationship()` na table
- Slug auto-gen via mutator no model
- Soft deletes (`SoftDeletes` trait + migration column)

#### `UserResource`

Vem out-of-the-box do framework via `arqel:install`. Cobre:

- Estado default sem custom code
- Password hashing
- (Se existir) `RoleScopeFilter` ou similar

### Navegação

- Group "Content" → Post + Category
- Group "System" → User
- Configurado via `Resource::$navigationGroup` ou `arqel:install` config

### Widget

1 widget `StatCard` no dashboard exibindo "Total Posts" — exercita o slot de widgets do Panel.

### Testes

Pest (se disponível, senão PHPUnit). Mínimo:

- `PostResourceTest` (4 testes: index, create, store, validation) — copiar do `apps/demo/`
- `CategoryResourceTest` (mesmo set)

### Critério de saída

Os 15 critérios do walkthrough original (do spec antecessor) passam num browser real, mas agora contra o pacote vindo do Packagist. Lista de critérios herdada:

1. Login com credenciais válidas
2. Login com credenciais inválidas exibe erro
3. Index `/admin/posts` lista records paginados
4. SelectFilter `status=published` filtra a lista
5. TernaryFilter `featured` filtra a lista
6. Paginação navega entre páginas
7. Form de create renderiza
8. Validation errors exibidas no submit inválido
9. Edit pré-popula dados
10. Delete exibe modal de confirmação
11. Bulk delete funciona
12. Theme toggle (dark/light) via Cmd+K
13. Command Palette abre via Cmd+K e navega
14. Search global filtra a lista
15. Sort por coluna funciona

---

## Fase 3 — Integração MCP

### Objectivo

Provar que `@arqel-dev/mcp-server@0.9.1` consegue introspectar e operar sobre o projeto externo, e que Claude Code (ou outro cliente MCP) consegue invocar as 7 tools.

### Configuração

Criar `~/.claude.json` (ou `.mcp.json` na raiz do projeto, conforme preferência do user):

```json
{
  "mcpServers": {
    "arqel": {
      "command": "npx",
      "args": ["-y", "@arqel-dev/mcp-server@0.9.1"],
      "env": {
        "ARQEL_PROJECT_PATH": "/home/diogo/PhpstormProjects/arqel-test"
      }
    }
  }
}
```

(Nome exacto da env var a confirmar contra `packages-js/mcp-server/src/laravel/resolve-project.ts`.)

### Smoke das 7 tools

1. `search_docs` — query "field types" → retorna chunks de docs
2. `get_adr` — id `001` → retorna ADR-001 (Inertia-only)
3. `get_api_reference` — symbol `Arqel\Core\Resources\Resource` → retorna excerto
4. `list_resources` — sem args → retorna `PostResource`, `CategoryResource`, `UserResource`
5. `describe_resource` — slug `posts` → retorna shape canónico (fields, table, form, actions)
6. `generate_resource` — gera `TagResource` novo (model + migration + resource) sem ajustes manuais
7. `generate_field` — gera snippet de field para inserir num resource existente

### Critério de saída

- `mcp__arqel__list_resources` retorna os 3 resources do projeto externo
- `mcp__arqel__generate_resource` produz `TagResource` que `php artisan arqel:doctor` aceita sem warnings e que renderiza index `/admin/tags` no browser sem erro

### Saída de emergência

Se MCP não enxerga o projeto externo, investigar `resolve-project.ts` — provavelmente um caminho de config ou env var diferente do esperado. Se a tool falha de outra forma, abrir ticket, hotfix → v0.9.2.

---

## Fase 4 — Validação e relatório

### Objectivo

Consolidar resultado de Fases 1-3 em relatório auditável e marcar (ou não) Fase 1 MVP como "validated end-to-end".

### Passos

1. Rodar `php artisan test` no projeto externo (todos os feature tests passando).
2. Rodar `php artisan arqel:doctor` final (zero warnings).
3. Browser walkthrough completo dos 15 critérios da Fase 2.
4. Smoke das 7 tools MCP da Fase 3.
5. Escrever `docs/superpowers/reports/2026-05-06-e2e-validation-report.md` no monorepo:
   - Cabeçalho: data, versão testada, ambiente
   - Resultado por critério (pass / fail / notes)
   - Bugs descobertos → cada um vira ticket no `PLANNING/` correspondente
   - Performance observations (build time, install time, primeira request)
   - Conclusão: framework está pronto para Fase 2 do roadmap, ou bloqueado em X
6. Commit do relatório na main do monorepo.

### Critério de saída

Relatório commitado. Se zero bugs novos, marcar Fase 1 MVP como "validated end-to-end" no `PLANNING/07-roadmap-fases.md` e `PLANNING/08-fase-1-mvp.md`.

---

## Fase 5 — Cleanup do monorepo

### Objectivo

Fechar tarefas pendentes do spec antecessor.

### Passos

1. `rm -rf apps/demo-old/` (Task 12 do spec antecessor).
2. Verificar `.gitignore` — restaurar se foi mexido durante o walkthrough.
3. Commit: `chore(demo): remove demo-old backup`.
4. Decidir destino do `~/PhpstormProjects/arqel-test/`:
   - **Opção A (recomendada):** manter sandbox local permanente, sem GitHub
   - **Opção B:** mover para `arqel-dev/arqel-test` no GitHub como exemplo público (decisão separada — implica custos, manutenção, expectativas de comunidade)
5. Atualizar `docs/tickets/current.md` → próxima sprint.

### Critério de saída

Working tree limpo no monorepo. `apps/demo/` é a única app de demo. Plano para próxima sprint definido.

---

## Riscos e mitigações

| Risco | Mitigação |
|-------|-----------|
| CI falha publicar v0.9.1 | Parar Fase 0, debugar, hotfix; sem v0.9.1 nada avança |
| Conflito de rebase em `composer.lock`/`package.json` | Resolver mantendo state da main + bumps em cima |
| Bug crítico no walkthrough externo | Hotfix → v0.9.2 → repetir Fase 0+1+2 |
| MCP não enxerga projeto externo | Debugar `resolve-project.ts`, confirmar env var, possivelmente novo release |
| Sub-packages no Packagist sem tags semânticas | Verificar resolução de `^0.9.1` — se falhar, ticket de release pipeline |
| Walkthrough manual lento | Aceitar — é o ponto da validação |

## Testes

- **PHP:** Pest no `arqel-test` (mínimo 8 testes feature)
- **Browser:** 15-critério walkthrough manual (no `arqel-test`, em browser real)
- **MCP:** smoke das 7 tools via stdio (`npx @arqel-dev/mcp-server` + JSON-RPC manual ou via Claude Code)
- **Monorepo:** suite existente continua a passar pós-rebase + bump

## Convenções

- Commits seguem Conventional Commits + DCO sign-off (regra do CLAUDE.md)
- Docs em PT-BR; código em inglês
- Spec antecessor referenciado em commits relevantes via "Implements [...]" no body

## Tasks (high-level)

A decomposição final fica para o writing-plans skill. High-level:

1. Fase 0 — Rebase + bump + tag v0.9.1 + verify publish
2. Fase 1 — Bootstrap `arqel-test` (Laravel new + composer require + install + doctor)
3. Fase 2 — PostResource + CategoryResource + UserResource + nav + widget + tests
4. Fase 3 — `.claude.json` MCP config + smoke das 7 tools
5. Fase 4 — Relatório
6. Fase 5 — Cleanup

---

## Glossário

- **Path repos:** `composer.json#repositories[].type === "path"` — symlinks locais. Usados no `apps/demo/` interno. NÃO usar no `arqel-test`.
- **Subtree split:** workflow CI que extrai cada `packages/*` para um repo Git próprio, permitindo `composer require arqel-dev/<sub>` independente.
- **MCP (Model Context Protocol):** protocolo JSON-RPC que dá a assistentes IA acesso a tools/resources/prompts.
- **Walkthrough:** validação manual em browser real seguindo lista de critérios.

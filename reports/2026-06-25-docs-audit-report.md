# Docs-audit loop — relatório final de convergência

**Data:** 2026-07-02
**Status:** ✅ Convergido
**Total de correções:** 172 (ledger `reports/docs-fixed-ledger.json`)
**Rounds:** 27 (2 rounds limpos íntegros consecutivos — R26 + R27 — satisfazem o critério de parada)

## Resumo executivo

Loop autônomo de auditoria de documentação (`detect → adversarial-verify → fix → PR → merge`) executado até que **toda** a documentação do monorepo Arqel ficasse consistente com o código real. Escopo coberto:

- **`apps/docs/**`** — site VitePress trilíngue (EN em `apps/docs/<path>`, pt-BR em `apps/docs/pt-BR/<path>`, es em `apps/docs/es/<path>`): reference PHP/TypeScript, guides, migration guides, examples.
- **`packages/*/README.md` e `packages/*/SKILL.md`** — pacotes PHP.
- **`packages-js/*/README.md` e `packages-js/*/SKILL.md`** — pacotes JS (`@arqel-dev/*`).

Cada round rodou detecção paralela em **3 dimensões** (factual-drift, broken-refs, stale-status), com **verificação adversarial refute-by-default** de cada achado contra a fonte, e **dedup acumulativo** contra um ledger crescente para nunca re-reportar uma família já varrida. Só correções verificadas contra o código-fonte entraram no ledger.

**Critério de parada:** 2 rounds íntegros limpos consecutivos (0 confirmados, com as 3 dimensões de detecção rodando por completo). Atingido em R26 + R27.

## Curva de convergência

Confirmados por round (1→27):

```
21 · 23 · 21 · 22 · 18 · 6 · 5 · 6 · 7 · 2 · 8 · 3 · 5 · 5 · 3 · 2 · 1 · 5 · 3 · 2 · 0 · 1 · 1 · 0 · 2 · 0 · 0
```

A cauda (`…2 · 0 · 1 · 1 · 0 · 2 · 0 · 0`) mostra o comportamento típico de convergência com **drenagem de famílias**: cada round limpo destravava uma última camada de resíduo isolado (SKILL de um pacote menos varrido, links de locale numa página) antes de zerar de novo. O critério de 2-limpos-consecutivos impediu paradas prematuras em R21 (limpo, mas R22 achou 1), R24 (limpo, mas R25 achou 2), até R26+R27 fecharem.

## As 8 famílias sistêmicas

1. **Signature / param-order / return-type / factory drift** em `apps/docs/reference/php/*.md` (nos 3 locales) — Column setters (Date/Badge/Icon/Relationship/Date/Image), `Confirmable` modal*ButtonLabel, `Action` visible/disabled/hidden, `ResourceRegistry::discover($path, $namespace)`, `LoginController` props, `FormRequestGenerator::generate()`, `Resource::indexQuery(): mixed`, `TableQueryBuilder` build()/__construct (não for()/paginate()), `TernaryFilter` column/trueLabel/falseLabel.
2. **Shipped-but-marked-pending** — features já entregues rotuladas "por chegar"/"ainda por chegar"/"pendente"/"planned"/"TBD"/"under review"/"slice futura" em SKILLs (ai, fields, widgets, auth, cli, versioning, tenant, export, workflow, docs-app) e guides (authentication, from-nova).
3. **Factory `make()` / constructor default & optionality drift** — `ExportAction::make(string $name)` (sem default), `TableQueryBuilder` `for()` → `__construct`.
4. **Peerdep / install-cmd drift + version-string-stale + runtime-deps-claim** — READMEs/SKILLs vs `package.json`/`composer.json`: react Inertia 3, ui radix-ui+recharts, versioning JS 0.15.0, theme depende de `@arqel-dev/react` em runtime.
5. **Count drift** — hooks=11, audit query-params=7, types entry-points=8.
6. **Artisan command lists** — core README, cli SKILL (arqel:install/resource/make-user; CLI-TUI-002/004 entregues).
7. **Cloud env-vars / config keys / entry-points / release / phases** — deploy docs.
8. **Inertia props-block + trilingual mirror + locale-prefix-link drift** — blocos de props do controller, drift entre mirrors en/pt-BR/es, e links sob `/pt-BR/`·`/es/` sem o prefixo de locale (roteando silenciosamente para o inglês).

## Correções por round

| Round | Confirmados | Área principal |
|---|---|---|
| 1 | 21 | reference PHP — assinaturas iniciais |
| 2 | 23 | reference + guides |
| 3 | 21 | READMEs + mirrors trilíngues |
| 4 | 22 | guides + deploy/config |
| 5 | 18 | Column API + guides |
| 6 | 6 | deploy env vars / config keys |
| 7 | 5 | export deps, lint tool, release script, phases |
| 8 | 6 | cloud env vars, config keys, entry points, splitsh |
| 9 | 7 | Column setter API + core README |
| 10 | 2 | widgets StatWidget setters + hooks README |
| 11 | 8 | react/ui peer deps + AI SKILL shipped-not-followup |
| 12 | 3 | cli/audit/widgets SKILL drift |
| 13 | 5 | actions.md setters + fields SKILL |
| 14 | 5 | ImageColumn/discover sigs + auth shipped-not-planned |
| 15 | 3 | auth.md Login props + action-form-modal |
| 16 | 2 | FormRequestGenerator example + versioning JS version |
| 17 | 1 | core.md indexQuery signature |
| 18 | 5 | tenant/export SKILL + docs SKILL DOCS-002..008 |
| 19 | 3 | table.md TableQueryBuilder API + TernaryFilter |
| 20 | 2 | cli SKILL CLI-TUI-002/004 |
| 21 | 0 | **limpo** |
| 22 | 1 | versioning PHP SKILL VersionDiff |
| 23 | 1 | theme SKILL runtime-deps |
| 24 | 0 | **limpo** |
| 25 | 2 | workflow SKILL WorkflowVisualizer + pt-BR locale links |
| 26 | 0 | **limpo** |
| 27 | 0 | **limpo** → convergência |

## Observações metodológicas

- **Verificação adversarial refute-by-default:** cada achado passou por um agente independente instruído a refutá-lo por padrão, abrindo doc + fonte, só marcando `isReal` quando a contradição era genuína, nova e acionável. Isso filtrou nitpicks estilísticos e itens genuinamente pendentes corretamente marcados como pendentes.
- **DEDUP acumulativo (growing ledger):** cada round carregava a lista de todas as famílias já varridas; achados dentro delas eram descartados como duplicados. Isso concentrou o esforço em camadas novas a cada round.
- **E2E flaky:** o step "Tests E2E (Playwright)" do stack showcase-dogfood às vezes falha no boot (pull de imagens Docker Hub), mas sempre passa em rerun; as suítes demo + tenant-demo sempre passaram. Todos os PRs foram docs-only (`.md` + `reports/*.json`), então nunca puderam causar falha real de código.
- **commitlint scope-enum:** apenas `docs` e `docs(qa)` são scopes válidos para documentação (o enum não aceita `reference`, `readme`, `skill`, etc.).
- **Regra de round limpo íntegro:** um round com 0 confirmados conta como limpo desde que as 3 dimensões de detecção rodem por completo — mesmo com `agentCount=3` (zero achados ⇒ zero verificadores), o que representa cobertura íntegra, não um run incompleto.

## Artefatos

- **Ledger:** `reports/docs-fixed-ledger.json` (172 assinaturas).
- **Detecções por round:** `reports/docs-round{10..25}-detection.json`.
- **PRs:** #308–#331 (rounds com achados geraram fix-PRs; R21/R24/R26/R27 foram limpos, sem PR de fix).

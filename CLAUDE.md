# CLAUDE.md — Arqel

> **Este arquivo é o contexto mestre para o Claude Code trabalhando neste repositório.**
> É lido automaticamente no início de cada sessão. Mantém-no conciso e atualizado.

## Visão geral

**Arqel** é um framework open-source MIT para construção de admin panels em Laravel, posicionado como competidor do Filament e Nova. Stack: Laravel 12+/13 + Inertia 3 + React 19.2+ + ShadCN CLI v4 (Base UI) + Tailwind v4 + TypeScript strict + Pest 3.

**Tagline:** Admin panels for Laravel, forged in PHP, rendered in React.

**Distribuição:**
- Composer: `arqel/*` em Packagist
- npm: `@arqel/*`
- Licença: MIT + DCO
- Domínio: `arqel.dev`
- GitHub org: `arqel`

## Modo operacional atual

**Fase:** Sprint 0 (Setup inicial)
**Modo:** Autonomous — decisões críticas paras para confirmação; resto segue sem intervenção
**Ponto de partida:** INFRA-001

**Ordem Sprint 0** (ver `PLANNING/08-fase-1-mvp.md` §2):
1. INFRA-001 — Criar monorepo com pnpm workspaces + composer path repositories
2. INFRA-002 — Configurar TypeScript base config + Vite + tsup
3. INFRA-003 — CI GitHub Actions com matrix PHP 8.3/8.4 × Laravel 12/13
4. INFRA-004 — Release pipeline (splitsh/lite + npm publish)
5. INFRA-005 — Pre-commit hooks (Husky + lint-staged)

Paralelo a tudo acima: GOV-001 (SECURITY.md) e GOV-003 (CONTRIBUTING.md + DCO bot).

## Planejamento completo

**13 documentos** em `PLANNING/` (22.335 linhas, 328 tickets). Ler na ordem indicada:

| Doc | Quando consultar |
|---|---|
| `00-index.md` | **Sempre primeiro** — convenções, estrutura SKILL.md, formato de ticket |
| `01-spec-tecnica.md` | Requisitos funcionais/não-funcionais (RF-*, RNF-*) |
| `02-arquitetura.md` | Diagramas C4, data flows |
| `03-adrs.md` | **18 ADRs** — decisões canônicas (não contradizer) |
| `04-repo-structure.md` | Layout do monorepo, estrutura de pacotes |
| `05-api-php.md` | APIs PHP públicas (Resource, Field, Table, Form, Action, Panel, Widget) |
| `06-api-react.md` | Types TypeScript, hooks, components React |
| `07-roadmap-fases.md` | Master plan 4 fases |
| `08-fase-1-mvp.md` | **Tickets ativos atuais** — 123 tickets Fase 1 |
| `09-fase-2-essenciais.md` | Fase 2 — 90 tickets (futuro) |
| `10-fase-3-avancadas.md` | Fase 3 — 70 tickets (futuro) |
| `11-fase-4-ecossistema.md` | Fase 4 — 45 tickets (futuro) |
| `12-processos-qa.md` | **Sempre consultar** — CI/CD, testes, release, governança |

**Regra crítica:** Se houver conflito entre `CLAUDE.md` e `PLANNING/`, **`PLANNING/` vence** (é a fonte canônica).

## Convenções obrigatórias

### Linguagem
- **Código**: inglês (nomes de classes, métodos, variáveis, comentários de código)
- **Documentação**: **português brasileiro (PT-BR)** — SKILL.md, README.md, docs em `PLANNING/`
- **Commits**: inglês (Conventional Commits — ver §Commits abaixo)
- **Comunicação humana**: **português brasileiro (PT-BR)** — usa "você/usuário/arquivo/otimizar", nunca "tu/utilizador/ficheiro/optimizar"

### Stack versions (não alterar sem consulta)
- **PHP**: 8.3+ (testado em 8.3 e 8.4)
- **Laravel**: 12+ (testado em 12.x e 13.x)
- **Node**: 20.9+ LTS (testado em 20 e 22)
- **React**: 19.2+
- **TypeScript**: 5.6+ com `strict: true`, `noUncheckedIndexedAccess: true`
- **PHPStan**: level max
- **Pest**: 3.x
- **ShadCN CLI**: v4 com Base UI como default registry
- **Tailwind**: v4 (syntax `@import 'tailwindcss';`)

### Inertia-only
- **Inertia 3** é a única ponte PHP↔React permitida (ADR-001)
- Nunca adicionar TanStack Query, SWR, ou outras fetch libs para Resource CRUD (ADR-016)
- Inertia props são o estado default

### Commits
Formato: **Conventional Commits** obrigatório.

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`

**Scopes:** nome do pacote (e.g., `feat(core): add panel registration`)

**Breaking changes:** `!` após type ou `BREAKING CHANGE:` no footer

**DCO sign-off obrigatório em todos commits:**
```bash
git commit --signoff -m "feat(core): ..."
```

Cada commit deve referenciar ticket correspondente no body:
```
feat(core): add ResourceRegistry singleton

Implements CORE-005 from PLANNING/08-fase-1-mvp.md
```

### Estrutura de ticket

Cada ticket em `PLANNING/08-*.md` até `11-*.md` tem formato:

```markdown
### [PACKAGE-###] Título

**Tipo:** feat • **Prioridade:** P0-P3 • **Estimativa:** XS-XL • **Camada:** php|react|shared|infra|docs • **Depende de:** [OUTRO-TICKET]

**Contexto** (por que existe)
**Descrição técnica** (o que fazer, com código exemplo)
**Critérios de aceite** (checkboxes verificáveis)
**Notas de implementação** (gotchas, trade-offs)
```

**Regra:** não marcar ticket completo até TODOS critérios de aceite terem checkbox ✅ + testes passando.

### Testes são obrigatórios

- Nenhum código novo sem testes (ADR-008)
- Coverage targets em `PLANNING/12-processos-qa.md` §2.2:
  - Core packages PHP: ≥90%
  - Core packages JS: ≥80%
- Pest (PHP) + Vitest (JS) + Playwright (E2E smoke set)

### SKILL.md per package

Cada pacote criado precisa `SKILL.md` na raiz com estrutura canônica (`PLANNING/00-index.md` §5):

```markdown
# SKILL.md — arqel/{package}

## Purpose
## Key Contracts
## Conventions
## Examples
## Anti-patterns
## Related
```

## Workflow do Claude Code

### Ao iniciar sessão
1. Ler este `CLAUDE.md` (feito automaticamente)
2. Ler `docs/tickets/current.md` para saber onde parou
3. Ler o ticket ativo em `PLANNING/08-fase-1-mvp.md` (ou fase correspondente)
4. Consultar ADRs relevantes em `PLANNING/03-adrs.md`
5. Começar implementação

### Para cada ticket
1. Ler ticket completo (contexto + descrição + critérios + notas)
2. Verificar dependências satisfeitas (tickets que ele depende estão completos)
3. Implementar seguindo convenções
4. Escrever testes (não opcional)
5. Rodar testes localmente: `vendor/bin/pest` + `pnpm test`
6. Rodar lint: `vendor/bin/pint` + `pnpm lint`
7. Rodar typecheck: `vendor/bin/phpstan analyse` + `pnpm typecheck`
8. Commit com DCO + Conventional format
9. Atualizar `docs/tickets/current.md` → próximo ticket
10. Continuar

### Quando PARAR e pedir confirmação humana

**Autonomous mode significa:** avança sem perguntar exceto nestes casos:

1. **Mudança em ADR** — se precisar contradizer uma decisão documentada
2. **Breaking changes** em API pública já implementada
3. **Dependência externa nova** não mencionada no plano (adicionar pacote composer/npm novo)
4. **Custos financeiros** — qualquer setup que gere cobrança (Cloudflare, AWS, Anthropic API, etc.)
5. **Segurança** — qualquer mudança em auth, permissions, crypto, secrets management
6. **Ambiguidade no ticket** — se interpretação não é óbvia
7. **Falha reprodutível** — teste falha inexplicavelmente 3+ vezes
8. **Escopo fora do ticket** — descobrir que ticket precisa de trabalho não planejado

Para cada um desses casos, **comentar em commit log + parar + aguardar decisão**.

### Quando NÃO parar (seguir em frente)

- Detalhes de implementação não especificados → decidir com base em ADRs e convenções do código
- Nomes de variáveis → escolher descritivos em inglês
- Formatação → aplicar lint standards
- Refactors necessários para ticket → fazer sem perguntar
- Typos em ticket → seguir interpretação mais provável

## Comandos úteis

```bash
# Setup inicial
./scripts/init.sh

# Rodar tudo (lint + tests + typecheck)
pnpm test:all

# PHP
vendor/bin/pest                 # Rodar testes PHP
vendor/bin/pest --coverage      # Com coverage
vendor/bin/pint                 # Lint PHP
vendor/bin/phpstan analyse      # Static analysis

# JS
pnpm test                       # Rodar testes JS
pnpm lint                       # ESLint
pnpm typecheck                  # tsc --noEmit
pnpm build                      # Build todos pacotes

# Avançar ticket
./scripts/next-ticket.sh        # Ver próximo ticket
```

## Convenções de comandos shell (evitar prompts de segurança)

Para não disparar guards de segurança do Claude Code que exigem aprovação manual mesmo em modo autonomous:

1. **Nunca usar `cd <path> && git ...`** — usar `git -C <path> <comando>` em vez disso.
   - ❌ `cd packages/core && git log -1`
   - ✅ `git -C packages/core log -1`

2. **Nunca combinar `cd` com redirecionamentos (`>`, `>>`, `2>/dev/null`, `2>&1`) num comando composto** — usar paths absolutos.
   - ❌ `cd worktrees/agent-x && ls docs/ 2>/dev/null`
   - ✅ `ls /caminho/absoluto/worktrees/agent-x/docs/ 2>/dev/null`

3. **Preferir comandos atómicos a chains com `&&`/`;`** — facilita matching da allowlist e evita guards compostos.

## Slash commands (Claude Code)

Custom commands disponíveis em `.claude/commands/`:

- `/next-ticket` — Carrega próximo ticket da fase ativa
- `/review-ticket` — Executa checklist de review antes de commit
- `/sprint-status` — Mostra progresso da sprint atual
- `/adr-check` — Valida que mudanças respeitam ADRs

## Checklist antes de commit

- [ ] Todos critérios de aceite do ticket ✅
- [ ] Testes escritos e passando
- [ ] Coverage target atingido
- [ ] Lint limpo (Pint + ESLint)
- [ ] Typecheck limpo (PHPStan + tsc)
- [ ] Sem `console.log`, `dd()`, `dump()`, `var_dump` deixados
- [ ] Documentação atualizada (SKILL.md se mudou API)
- [ ] Commit message: Conventional format + DCO signoff
- [ ] Referência ao ticket no commit body

## Regras de ouro

1. **ADRs são canônicos** — não contradizer sem RFC
2. **Tests first** — PR sem testes = rejeitado
3. **Inertia-only** para PHP↔React (ADR-001)
4. **Laravel-native** — usar features do Laravel antes de reinventar (Policies, FormRequest, Eloquent, Gate, etc.)
5. **Final by default** — classes PHP `final` exceto quando extensibilidade é design intent
6. **declare(strict_types=1)** em todos arquivos PHP
7. **Sem dependencies surpresa** — só adicionar pacotes que estão no plano
8. **Português-BR em docs** — nunca português-PT
9. **Paraphrase sempre** — nunca copiar código de outras fontes sem licença MIT-compat verificada
10. **Se em dúvida, PARE e pergunte** — mas seguindo as regras de autonomous mode acima

---

**Última atualização:** 2026-04-17
**Próximo ticket:** ver `docs/tickets/current.md`

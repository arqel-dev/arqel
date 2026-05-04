# Contribuir para o Arqel

Bem-vindo! O Arqel é um framework open-source MIT mantido pela comunidade. Este guia explica em detalhe como começar a contribuir, do primeiro `git clone` ao primeiro PR aprovado.

> Resumo executivo no [`CONTRIBUTING.md`](https://github.com/arqel/arqel/blob/main/CONTRIBUTING.md) raiz. Este documento expande com mais contexto, exemplos e gotchas.

## Por que contribuir

O Arqel existe para tornar admin panels Laravel + React tão produtivos quanto Filament e Nova, mas com uma stack moderna (React 19.2, Inertia 3, TypeScript strict, Base UI). Cada contribuição:

- **Acelera o ecossistema Laravel** ao oferecer alternativa first-class ao Filament/Nova
- **Reduz dívida técnica** em projetos que dependem de painéis admin
- **Constrói reputação** — autores são creditados em release notes e podem virar mantenedores
- **Aprende-se muito** — o repo combina PHP moderno, React 19.2, Inertia, monorepo, CI matrix, splitsh, e patterns de framework design

Não importa o tamanho da contribuição: typo fix, novo Field, vertical inteiro — tudo é bem-vindo desde que respeite os padrões deste documento.

## Antes de começar

Leia, na ordem:

1. [`README.md`](https://github.com/arqel/arqel/blob/main/README.md) — visão geral do projeto.
2. [`CLAUDE.md`](https://github.com/arqel/arqel/blob/main/CLAUDE.md) — convenções operacionais (linguagem, stack, commits).
3. [`PLANNING/00-index.md`](https://github.com/arqel/arqel/blob/main/PLANNING/00-index.md) — estrutura do plano.
4. [`PLANNING/03-adrs.md`](https://github.com/arqel/arqel/blob/main/PLANNING/03-adrs.md) — 18 ADRs canônicos. **Não contradizer sem RFC.**
5. [`CODE_OF_CONDUCT.md`](https://github.com/arqel/arqel/blob/main/CODE_OF_CONDUCT.md).

Se a sua contribuição for uma feature nova grande, **abra primeiro uma discussão** em [GitHub Discussions](https://github.com/arqel/arqel/discussions) ou uma issue com label `rfc`. Isto evita retrabalho.

## Pré-requisitos

| Ferramenta | Versão mínima | Notas |
|---|---|---|
| PHP | 8.3 | Testado em 8.3 e 8.4. PHPStan level max. |
| Composer | 2.x | — |
| Node | 20.9 LTS | Recomendado v22 (`.nvmrc`). |
| pnpm | 10+ | `corepack enable pnpm`. |
| Git | 2.30+ | Para `--signoff` e worktrees. |

Extensões PHP necessárias: `mbstring`, `intl`, `pdo_mysql`, `pdo_pgsql`, `redis`, `zip`, `bcmath`.

## Setup completo (passo a passo)

### 1. Fork + clone

```bash
# No GitHub: arqel/arqel → Fork
git clone https://github.com/<seu-user>/arqel.git
cd arqel
git remote add upstream https://github.com/arqel/arqel.git
```

### 2. Selecionar versão de Node

```bash
nvm use   # lê .nvmrc
corepack enable pnpm
```

### 3. Instalar dependências

```bash
./scripts/init.sh
```

O script faz:

- `composer install` no root e em cada `packages/*` que tenha `composer.json`.
- `pnpm install` no root (workspaces).
- Configura hooks Husky (`commit-msg`, `pre-commit`).
- Roda smoke tests para validar que o setup funciona.

### 4. Verificar o setup

```bash
pnpm run lint        # Biome no JS/TS
pnpm run typecheck   # tsc --noEmit em cada workspace
pnpm run test        # Vitest

vendor/bin/pint --test         # Pint sem aplicar
vendor/bin/phpstan analyse     # Level max
vendor/bin/pest                # Pest 3
```

Se algum comando falhar antes de você fazer mudanças, abra uma issue — o setup deveria estar limpo no `main`.

## Workflow de PR

### 1. Crie um branch

Convenção de naming: `<tipo>/<scope>-<descrição-curta>`.

```bash
git checkout -b feat/fields-add-color-picker
git checkout -b fix/table-pagination-edge-case
git checkout -b docs/guide-update-realtime-section
```

Tipos válidos (alinhados com Conventional Commits): `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `chore`, `ci`, `build`, `style`.

### 2. Implemente a mudança

- **Tests first** quando possível (Pest para PHP, Vitest para JS).
- Mantenha cobertura: ≥90% para pacotes PHP core, ≥80% para JS core.
- Atualize `SKILL.md` do pacote se a API pública mudar.
- Atualize `apps/docs/` se houver mudança visível ao usuário final.

### 3. Rode o checklist local

```bash
pnpm test:all                  # lint + typecheck + tests, tudo
vendor/bin/pint                # aplica Pint
vendor/bin/phpstan analyse     # level max
vendor/bin/pest --coverage     # com coverage
```

### 4. Commit com Conventional Commits + DCO

**DCO sign-off é obrigatório** — sem ele o PR é rejeitado pelo bot.

```bash
git commit --signoff -m "feat(fields): add ColorField with preset palette

Implements FIELDS-042 from PLANNING/08-fase-1-mvp.md.

- Suporta paleta customizada via prop palette
- Preview clicável abre BasePicker
- Test coverage: 95%
"
```

Formato:

```
<type>(<scope>): <description>

[corpo opcional explicando "porquê"]

[footer com referência ao ticket: Implements FOO-001]
```

Scopes comuns: nome do pacote (`core`, `fields`, `table`, `marketplace`, `ai`, `realtime`, `ui`, `react`, `docs`, `ci`).

### 5. Sincronizar com upstream

```bash
git fetch upstream
git rebase upstream/master
```

Use rebase, não merge — mantém histórico linear.

### 6. Abra o PR

- Title em formato Conventional Commits.
- Preencha o template `.github/PULL_REQUEST_TEMPLATE.md`.
- Marque "Allow edits from maintainers".
- Linke a issue ou ticket relacionado.
- Se houver UI, anexe screenshots ou GIFs.

### 7. Code review

- Pelo menos **1 mantenedor** deve aprovar.
- CI deve passar (matrix PHP × Laravel, lint, typecheck).
- Resolva todos os comentários antes de merge.
- Se o PR ficar parado >7 dias, comente pingando os mantenedores.

### 8. Merge

Mantenedores fazem squash merge para manter histórico limpo. A mensagem final segue o título do PR.

## Style guide

### PHP

- `declare(strict_types=1);` em todos os arquivos.
- Classes `final` por padrão. Use `abstract` ou `extends` apenas quando extensibilidade for design intent.
- Usar features Laravel-native (Policy, FormRequest, Eloquent, Gate) antes de reinventar.
- Respeitar `pint.json` (preset Laravel + ajustes do projeto).
- PHPStan level max — nada de `mixed` sem necessidade.

### TypeScript / React

- `strict: true` + `noUncheckedIndexedAccess: true` (já em `tsconfig.base.json`).
- Componentes funcionais sempre. Sem class components.
- Hooks: prefixo `use`, regras do React em modo estrito.
- Tipos exportados em `@arqel/types`. Nunca duplicar entre pacotes.
- ESLint via Biome (`biome.json`).

### Inertia-only (ADR-001)

A única ponte PHP↔React é Inertia 3. **Não adicionar** TanStack Query, SWR, Axios, fetch wrappers para Resource CRUD. Inertia props são o estado padrão.

### Documentação

- PT-BR (não PT-PT). Use "você", "usuário", "arquivo", "otimizar".
- Código em inglês (nomes de classes, variáveis, comentários inline).
- Exemplos completos e executáveis quando possível.

## Como adicionar um novo pacote

1. Cole a estrutura em `packages/<nome>/` (PHP) ou `packages-js/<nome>/` (JS).
2. Adicione `composer.json` ou `package.json` seguindo padrão dos pacotes existentes.
3. Crie `SKILL.md` com a estrutura canônica (`PLANNING/00-index.md` §5):
   - Purpose, Key Contracts, Conventions, Examples, Anti-patterns, Related.
4. Adicione testes (`tests/` PHP + `*.test.ts` JS).
5. Atualize:
   - `pnpm-workspace.yaml` (se JS).
   - Root `composer.json` `repositories` (se PHP, path repo).
   - `apps/docs/.vitepress/config.ts` se for visível na docs.
   - `.github/labeler.yml` adicionando regra para o novo pacote.
   - `CODEOWNERS` adicionando linha apropriada.
6. Abra PR com label `new-package`.

## Como propor um novo ticket no PLANNING

Tickets vivem em `PLANNING/08-*.md` (Fase 1) até `PLANNING/11-*.md` (Fase 4). Para propor:

1. Abra issue com label `proposal-ticket` descrevendo: contexto, problema, proposta de API, critérios de aceite.
2. Discussão em Discussions ou na issue.
3. Após aprovação, mantenedor adiciona o ticket ao arquivo correto seguindo o template:

```markdown
### [PACKAGE-###] Título

**Tipo:** feat • **Prioridade:** P0-P3 • **Estimativa:** XS-XL • **Camada:** php|react|shared|infra|docs • **Depende de:** [OUTRO-TICKET]

**Contexto** (porquê existe)
**Descrição técnica** (o que fazer + código exemplo)
**Critérios de aceite** (checkboxes)
**Notas de implementação** (gotchas)
```

## Rodar diagnósticos antes do PR

Dois comandos úteis (disponíveis após Phase 1):

```bash
php artisan arqel:doctor    # Verifica versões, configs, integridade do panel
php artisan arqel:audit     # Audita Resources/Fields contra ADRs
```

Anexe a saída no PR se a mudança tocar integração entre pacotes.

## Onde discutir antes do PR

- **GitHub Discussions** — perguntas, RFCs informais, brainstorming.
- **Issues com label `rfc`** — RFCs formais para mudanças de API.
- **Discord** (link no README quando disponível) — chat rápido.

## Common gotchas

- **DCO esquecido**: rebase com `git rebase --signoff -i HEAD~N` para retroativar sign-offs.
- **PHPStan max falhando em código novo**: PHPStan agora é estrito; valide com `vendor/bin/phpstan analyse` antes de push.
- **Biome reclamando em arquivos não tocados**: rode `pnpm run lint:fix` apenas nos seus arquivos com `--files-ignore-unknown=true` ou explicitamente.
- **Composer path repos não atualizam**: rode `composer update arqel/*` no root para puxar mudanças locais.
- **Husky não roda hooks**: confirme que o setup chamou `pnpm run prepare` (instala hooks).
- **Testes do matrix de PostgreSQL falham localmente**: o CI usa serviço dedicado; localmente prefira MySQL ou rode `docker compose up postgres` se houver `compose.yml`.

## Reconhecimento

Contribuidores são listados automaticamente via [all-contributors](https://allcontributors.org/) (será habilitado pré-`v1.0`). Mantenedores ativos ganham acesso a triagem e merge após 5+ PRs aprovados ou convite explícito.

## Suporte e dúvidas

- Bug ou comportamento inesperado: [issue com template `bug_report`](https://github.com/arqel/arqel/issues/new?template=bug_report.yml).
- Pergunta de uso: [Discussions](https://github.com/arqel/arqel/discussions) ou template `question`.
- Vulnerabilidade de segurança: **NÃO** abra issue pública — siga [`SECURITY.md`](https://github.com/arqel/arqel/blob/main/SECURITY.md).

Obrigado por contribuir com o Arqel!

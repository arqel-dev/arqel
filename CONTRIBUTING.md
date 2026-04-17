# Contribuir para Arqel

Obrigado por considerares contribuir! 🎉 Este documento explica **como**.

> Antes de começar, lê também [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) e [`SECURITY.md`](SECURITY.md). O planeamento completo está em [`PLANNING/`](PLANNING/) e as convenções operacionais em [`CLAUDE.md`](CLAUDE.md).

## Tipos de contribuição

Aceitamos:

- 🐛 **Bug reports** — usa o template em `.github/ISSUE_TEMPLATE/bug_report.yml`
- 💡 **Feature requests** — template `feature_request.yml` (prefere-se discussão primeiro em GitHub Discussions)
- ❓ **Perguntas** — GitHub Discussions ou template `question.yml`
- 📝 **Documentação** — PRs para `docs-content/` e READMEs
- 🧪 **Testes** — adicionar cobertura em áreas mal cobertas
- ✨ **Features novas** — apenas após discussão aberta em issue/RFC

**Antes de abrir PRs grandes, abre uma issue** para alinhar a abordagem.

## Configurar o ambiente

### Pré-requisitos

- PHP **8.3+** (testado em 8.3 e 8.4)
- Composer **2.x**
- Node **20.9+ LTS** (recomendamos v22 — vê `.nvmrc`)
- pnpm **10+** (habilitado via `corepack enable pnpm`)
- Git **2.30+**

### Setup inicial

```bash
# Fork no GitHub, depois:
git clone https://github.com/<your-user>/arqel.git
cd arqel

# Activa a versão de Node do projecto
nvm use  # lê .nvmrc

# Setup automático
./scripts/init.sh
```

O `init.sh` instala `pnpm` via `corepack`, deps Composer e npm, configura hooks (husky) e corre testes iniciais.

### Verificar setup

```bash
pnpm run lint        # biome check
pnpm run typecheck   # tsc --noEmit em workspace
vendor/bin/pint --test
composer run analyse # phpstan (skip gracioso se ainda não há packages)
```

Tudo verde? Estás pronto.

## Fluxo de trabalho

### 1. Pega num ticket

Os tickets vivos estão em [`docs/tickets/current.md`](docs/tickets/current.md). Toda a roadmap está em [`PLANNING/08-fase-1-mvp.md`](PLANNING/08-fase-1-mvp.md) a [`11-fase-4-ecossistema.md`](PLANNING/11-fase-4-ecossistema.md).

Regras:

- **Dependências satisfeitas** — se o ticket depende de outro, esse precisa de estar ✅
- **Comenta na issue** a reservar antes de começar
- **Escopo** limitado ao ticket; não acumules refactors não relacionados

### 2. Cria uma branch

```bash
git checkout -b feat/CORE-005-resource-registry
# ou fix/TABLE-012-empty-state-flash
```

Convenção: `<type>/<TICKET-ID>-<slug-curto>`

### 3. Implementa

- **Código em inglês** (nomes de classes, métodos, variáveis, comentários de código)
- **Docs em Português-BR** (SKILL.md, README.md em packages, `PLANNING/`)
- **`declare(strict_types=1)` em todos os ficheiros PHP**
- **Classes PHP `final` por default** — só omite `final` se extensibilidade for design intent
- **TypeScript strict** — `strict: true`, `noUncheckedIndexedAccess`
- Segue as convenções do [`tsconfig.base.json`](tsconfig.base.json) e [`pint.json`](pint.json)

**Tests first.** Nenhum PR passa sem:

- Testes Pest para PHP
- Testes Vitest para JS/TS
- Coverage mínimo: **90% em core packages PHP**, **80% em core packages JS** (ver `PLANNING/12-processos-qa.md` §2.2)

Para mudanças de UI: abrir o dev server, testar a feature no browser (golden path + edge cases) antes de marcar como pronto.

### 4. Commits

Seguimos **Conventional Commits** + **DCO sign-off**. Ambos enforçados por hooks (`.husky/commit-msg`).

```bash
git commit --signoff -m "feat(core): add ResourceRegistry singleton

Implements CORE-005 from PLANNING/08-fase-1-mvp.md
"
```

**Formato:**

```
<type>(<scope>): <descrição curta no imperativo>

[body opcional — explica o *porquê*, não o *o quê*]

[footer opcional — breaking changes, refs, etc.]

Signed-off-by: Teu Nome <teu@email>
```

**Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`

**Scopes:** nomes de packages (`core`, `fields`, `table`, `form`, `actions`, `auth`, `nav`, `react`, `ui`, `hooks`, `types`) ou áreas transversais (`infra`, `gov`, `docs`, `demo`, `qa`, `tickets`, `deps`, `release`). Lista completa em [`commitlint.config.mjs`](commitlint.config.mjs).

**Breaking changes:** adiciona `!` após o type ou `BREAKING CHANGE:` no footer.

**Cada commit** deve referenciar o ticket correspondente no body:

```
Implements CORE-005 from PLANNING/08-fase-1-mvp.md
```

#### DCO — o que é?

O **Developer Certificate of Origin** é uma declaração simples de que tens direito a submeter o código. Não transferes copyright (não é CLA). Basta adicionar a linha `Signed-off-by:` via `git commit --signoff`. Ver [developercertificate.org](https://developercertificate.org).

### 5. Push + PR

```bash
git push -u origin feat/CORE-005-resource-registry
gh pr create --fill
```

Preenche o [PR template](.github/PULL_REQUEST_TEMPLATE.md) — é um checklist curto.

### 6. Code review

- Pelo menos **1 approving review** antes de merge
- CI tem de estar **verde** (lint, typecheck, testes, matrix para PHP)
- **Conversations resolvidas** antes de merge
- Commits podem ser squashed via botão GitHub, preservando DCO do autor

## ADRs e decisões arquiteturais

As **18 ADRs** em [`PLANNING/03-adrs.md`](PLANNING/03-adrs.md) são canónicas. Exemplos:

- ADR-001: Inertia.js como única bridge PHP↔React
- ADR-008: Pest 3 como test runner
- ADR-017: Laravel Policies como authorization canónica

**Se tua contribuição contradiz uma ADR**, abre uma **issue de RFC** primeiro. A ADR pode ser atualizada ou substituída via novo ADR; nunca contradita por um PR directo.

## Escrever testes

### Pest (PHP)

```php
it('registers a resource', function () {
    $registry = new ResourceRegistry();
    $registry->register(UserResource::class);

    expect($registry->all())->toHaveCount(1);
});
```

- `tests/Unit/` — isolados, rápidos, sem HTTP
- `tests/Feature/` — Orchestra Testbench, hits Laravel stack
- Factories em `tests/Factories/` reaproveitáveis

### Vitest (JS/TS)

```ts
import { describe, it, expect } from 'vitest';
import { resolveLayout } from './layoutResolver';

describe('resolveLayout', () => {
  it('returns default layout when none provided', () => {
    expect(resolveLayout(undefined)).toBe('default');
  });
});
```

- Co-localizar: `foo.ts` + `foo.test.ts`
- `@testing-library/react` para componentes
- Mocks mínimos — prefere fixtures reais

### Playwright (E2E)

Para flows críticos (login, criar Resource, submeter Form). Usa `apps/playground/` como base.

## Escrever documentação

- **SKILL.md** em cada package — estrutura em [`PLANNING/00-index.md`](PLANNING/00-index.md) §5
- **README.md** em cada package — visão rápida + exemplo
- **`docs-content/`** — conteúdo MDX para o site público (Fase 2+)

**Português-BR**, nunca PT-PT. Exemplos:

| ✅ PT-BR | ❌ PT-PT |
|---|---|
| você, usuário | tu, utilizador |
| arquivo | ficheiro |
| otimizar | optimizar |
| time (equipe) | equipa |

## O que NÃO fazer

- ❌ **Sem mocking de DB em testes de integração** — usa SQLite in-memory ou o container MySQL/Postgres
- ❌ **Sem adicionar dependências** não listadas no plano — abre RFC primeiro
- ❌ **Sem copiar código** de outros projectos sem verificar licença (apenas MIT/Apache-2.0/BSD-*)
- ❌ **Sem `console.log`, `dd()`, `dump()`, `var_dump`** deixados no código
- ❌ **Sem comentários que só dizem WHAT** — só comenta WHY quando não-óbvio
- ❌ **Sem refactors "while I'm here"** que expandem o escopo

## Pergunta difícil?

- Abre discussão em [GitHub Discussions](https://github.com/arqel/arqel/discussions)
- Marca um maintainer em issues complexas
- **Sem respostas em 7 dias?** Bump com `@arqel/maintainers`

## Reconhecimento

Todos os contributors aparecem no `CONTRIBUTORS.md` (gerado automaticamente) e no All Contributors bot.

Agradecemos o tempo e o cuidado 💜

---

**Última actualização:** 2026-04-17 (GOV-003)

# Arqel

> Admin panels for Laravel, forged in PHP, rendered in React.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-early%20development-orange.svg)]()

## Status

🚧 **Em desenvolvimento.** Não pronto para produção.

Fases 1, 2 e 3 fechadas. Fase 4 em andamento (DevTools extension, CLI, marketplace, Laravel Cloud). Ver `docs/tickets/current.md` para o snapshot atual.

## O que é Arqel

Framework open-source MIT para construir admin panels em Laravel com UI React moderna. Posicionamento: **Filament/Nova reimaginado com React 19.2+** e foco em:

- **Laravel-native**: usa Policies, FormRequest, Eloquent, Gate nativamente
- **Inertia 3 como única ponte**: PHP declara, React renderiza, zero boilerplate
- **TypeScript first-class**: types gerados e fornecidos para todos contratos
- **Base UI + ShadCN CLI v4**: componentes acessíveis, customizáveis por copy-paste
- **AI-native**: MCP server oficial + AI fields desde Fase 2-3
- **Real-time**: collaborative editing via Laravel Reverb + Yjs

Ver `PLANNING/01-spec-tecnica.md` para spec completa.

## Stack

- PHP 8.3+ · Laravel 12+ · Pest 3
- React 19.2+ · TypeScript 5.6+ strict · Inertia 3
- Tailwind v4 · Base UI · ShadCN CLI v4
- Vite 6 · pnpm workspaces · Composer path repositories
- GitHub Actions · splitsh/lite para monorepo

## Começar

> ⚠️ **Autenticação não vem incluída.** Arqel **não publica** páginas de `/login`/`/register` por design — você usa um starter kit Laravel (Breeze, Jetstream ou Fortify) ou implementa o seu próprio. Recomendado: **Breeze + React + Inertia**, que é o que `arqel new` instala por default. Ver [`apps/docs/guide/authentication.md`](apps/docs/guide/authentication.md). _Nota: estamos planeando shipar um fluxo Inertia-React opt-in dentro do `arqel-dev/auth` — tickets AUTH-006/007/008 (TBD)._

### Pré-requisitos

- **PHP** 8.3+ (testado em 8.3 e 8.4)
- **Composer** 2.x
- **Node** 20.9+ LTS (recomendado 22.x — ver [`.nvmrc`](.nvmrc))
- **pnpm** 10+ (habilitado automaticamente via `corepack`)
- **Git** 2.30+

### Setup

```bash
git clone https://github.com/arqel-dev/arqel.git
cd arqel

# Se usas nvm, fixa a versão do projecto
nvm use

# Setup automático (instala pnpm via corepack, composer deps, etc.)
./scripts/init.sh
```

Comandos principais após setup:

```bash
pnpm install                    # Instala deps JS do workspace
composer install                # Instala deps PHP (path repositories)
pnpm build                      # Build de todos os packages
pnpm test                       # Testes de todos os packages
pnpm lint                       # Lint de todos os packages
pnpm typecheck                  # tsc --noEmit em todos os packages
pnpm test:all                   # lint + typecheck + test (tudo)
```

### Desenvolvimento

```bash
# Rodar tudo
pnpm test:all

# Ou individualmente
pnpm dev          # Watch mode
pnpm test         # Testes
pnpm lint         # Lint
pnpm build        # Build packages
```

## Estrutura do monorepo

```
arqel/
├── packages/           # Pacotes PHP (Composer)
│   ├── core/
│   ├── fields/
│   ├── table/
│   ├── form/
│   ├── actions/
│   ├── auth/
│   └── nav/
├── packages-js/        # Pacotes JS (npm)
│   ├── types/
│   ├── react/
│   ├── hooks/
│   ├── ui/
│   └── fields/
├── apps/               # Aplicações
│   ├── docs/           # Documentation site
│   ├── playground/     # Dev playground
│   └── demo/           # Demo showcase
└── PLANNING/           # Planejamento completo (13 docs)
```

Detalhes em `PLANNING/04-repo-structure.md`.

## Roadmap

| Fase | Release | Duração | Status |
|---|---|---|---|
| Fase 1 — MVP | v0.5-beta | 4-7 meses | 🚧 Em progresso |
| Fase 2 — Essenciais | v0.8-rc | 4-7 meses | 📋 Planejado |
| Fase 3 — Avançadas | v1.0 LTS | 7-10 meses | 📋 Planejado |
| Fase 4 — Ecossistema | v1.x+ | 12+ meses | 📋 Planejado |

Total: **328 tickets detalhados** em `PLANNING/08-*.md` a `PLANNING/11-*.md`.

## Contribuir

Contribuições são bem-vindas! Ver [`CONTRIBUTING.md`](CONTRIBUTING.md) (em breve — ticket GOV-003) e [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md).

**Requisitos:**
- DCO sign-off em todos os commits: `git commit --signoff`
- Conventional Commits: `feat(pkg): description`
- Testes obrigatórios
- ADRs respeitados (ver `PLANNING/03-adrs.md`)

**Segurança:** ver [`SECURITY.md`](SECURITY.md) para a política de divulgação responsável.

## Documentação

- **Planejamento:** `PLANNING/` (13 docs, fonte canônica)
- **Para AI agents:** `AGENTS.md`, `CLAUDE.md`
- **Site público:** https://arqel.dev (em breve)

## Licença

MIT — ver [`LICENSE`](LICENSE).

## Créditos

Inspirado por:
- [Filament](https://filamentphp.com) — pela definição do padrão Laravel admin panel
- [Laravel Nova](https://nova.laravel.com) — pelo modelo Resource
- [React Admin](https://marmelab.com/react-admin/) — pelo modelo client-side de admin

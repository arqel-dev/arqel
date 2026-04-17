# Arqel

> Admin panels for Laravel, forged in PHP, rendered in React.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-early%20development-orange.svg)]()

## Status

🚧 **Em desenvolvimento inicial.** Não pronto para produção.

Fase atual: **Sprint 0 (Setup)** — a bootstrapar infraestrutura do monorepo.

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

### Pré-requisitos

- PHP 8.3+
- Composer 2.x
- Node 20.9+ (LTS)
- pnpm 9+

### Setup

```bash
git clone https://github.com/arqel/arqel.git
cd arqel
./scripts/init.sh
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

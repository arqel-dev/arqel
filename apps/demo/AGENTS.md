# AGENTS.md — Laravel

> Contexto operacional para agentes de IA (Claude Code, Copilot, Cursor, etc.)
> trabalhando neste projeto. Este ficheiro é **lido automaticamente** no início
> de cada sessão.

## Project overview

Esta aplicação usa **Arqel** (dev-main) — admin panels declarativos em
PHP, renderizados em React via Inertia. Stack:

- **PHP** 8.4+ • **Laravel** 13.8.0+
- **Inertia** 3 + **React** 19+ + **Tailwind** v4
- Arqel Resources vivem em `app/Arqel/Resources/`
- Pages Inertia geradas em `resources/js/Pages/Arqel/`

## Key conventions

- **Inertia-only:** nunca adicionar TanStack Query, SWR, ou outras fetch libs
  para CRUD de Resources (ADR-016 do Arqel)
- **Resources são a fonte da verdade:** UI deriva da definição em PHP
- **Tests first:** features novas exigem Pest (PHP) + Vitest (JS)
- **Final by default:** classes PHP são `final` exceto onde extensibilidade
  é design intent
- `declare(strict_types=1)` em todos os ficheiros PHP novos

## Commands

```bash
# Scaffold de um novo Resource
php artisan arqel:resource <Model>

# Rodar testes
vendor/bin/pest
npm run test

# Build assets
npm run build

# Dev server
npm run dev
php artisan serve
```

## Architecture

```
app/
├── Arqel/
│   ├── Resources/      ← Definições de Resource (CRUD declarativo)
│   └── Widgets/        ← Widgets de dashboard
├── Providers/
│   └── ArqelServiceProvider.php  ← Regista o painel desta app
config/
└── arqel.php           ← Path, guard, namespaces
resources/js/
└── Pages/Arqel/        ← Pages Inertia (auto-resolvidas)
```

## Recursos adicionais

- Documentação: https://arqel.dev/docs
- Issues: https://github.com/arqel/arqel/issues
- SKILL.md de cada package Arqel está em `vendor/arqel/*/SKILL.md`

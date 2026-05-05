# AGENTS.md — Arqel

> Este arquivo fornece contexto para qualquer agente AI (Claude Code, Cursor, Windsurf, Aider, etc.) trabalhando neste projeto. É padrão [agents.md](https://agents.md/) comunitário.

## Projeto

**Nome:** Arqel
**Descrição:** Framework open-source MIT para admin panels Laravel com Inertia+React
**Status:** Em desenvolvimento ativo (Fase 1, Sprint 0)
**Licença:** MIT + DCO
**Repositório canônico:** https://github.com/arqel-dev/arqel (placeholder)
**Documentação:** https://arqel.dev (placeholder)

## Stack

- **Backend:** PHP 8.3+, Laravel 12+, Pest 3
- **Frontend:** React 19.2+, TypeScript 5.6+, Inertia 3, Tailwind v4, Radix UI, shadcn CLI v4 (new-york)
- **Build:** Vite 6, tsup, pnpm workspaces, Composer path repositories
- **CI:** GitHub Actions com matrix PHP × Laravel × DB

## Conceitos centrais

- **Panels** — agrupamento de Resources sob uma rota base (`/admin`).
- **Resources** — definição declarativa CRUD para um Eloquent model.
- **Fields** — atributos de Resource (texto, select, relação, etc.) com rendering React.
- **AppShell** — layout top-level (sidebar + topbar + content) construído sobre primitives shadcn/Radix.
- **Sidebar shadcn** — usa o componente `Sidebar` da registry shadcn (new-york), com Radix Dialog para o overlay drawer mobile.

## Instalação em apps Laravel

Fluxo one-line (gerado pelo `arqel:install`):

```bash
composer require arqel-dev/arqel
php artisan arqel:install
```

O comando publica config, registra o panel default, gera o `AppShell` em `resources/js/`, instala primitives shadcn (new-york) e cria um `AGENTS.md` na raiz da app a partir de `packages/core/stubs/agents.stub`.

> Nota: o `AGENTS.md` no top-level deste monorepo é fonte para LLMs trabalhando *no próprio Arqel*; ele não é gerado pelo `arqel:install` — esse comando gera um AGENTS.md na app consumidora a partir do stub.

## Comandos frequentes

```bash
# Instalar dependências (após clone)
./scripts/init.sh

# Desenvolver
composer install && pnpm install
pnpm dev                              # Watch mode para todos pacotes

# Testar
vendor/bin/pest                       # PHP tests
pnpm test                             # JS tests
pnpm test:all                         # Tudo + lint + typecheck

# Lint / formatar
vendor/bin/pint                       # PHP format
pnpm lint                             # JS lint
pnpm lint:fix                         # Auto-fix

# Análise estática
vendor/bin/phpstan analyse            # PHP level max
pnpm typecheck                        # tsc --noEmit

# Build para release
pnpm build                            # Todos npm packages
./scripts/release.mjs --dry-run       # Simular release
```

## Estrutura do repositório

Ver `PLANNING/04-repo-structure.md` para detalhes. Resumo:

```
arqel/                              # Raiz do monorepo
├── CLAUDE.md                       # Contexto mestre para Claude Code
├── AGENTS.md                       # Este arquivo
├── PLANNING/                       # 13 docs de planejamento (fonte canônica)
├── packages/                       # Pacotes PHP (Composer)
│   ├── core/
│   ├── fields/
│   ├── table/
│   ├── form/
│   ├── actions/
│   ├── auth/
│   ├── nav/
│   └── ... (mais em fases futuras)
├── packages-js/                    # Pacotes JS (npm)
│   ├── types/
│   ├── react/
│   ├── hooks/
│   ├── ui/
│   └── fields/
├── apps/                           # Aplicações
│   ├── docs/                       # Site de documentação (VitePress)
│   ├── playground/                 # App Laravel para testes
│   └── demo/                       # Demo showcase
├── docs/                           # Docs internos do monorepo
│   └── tickets/                    # Tracking de tickets
├── scripts/                        # Scripts de dev/release
├── .github/                        # GitHub Actions + templates
└── .claude/                        # Claude Code config
```

## Convenções críticas

Resumo aqui — detalhes em `CLAUDE.md` e `PLANNING/12-processos-qa.md`.

1. **Inertia-only** para ponte PHP↔React (ADR-001)
2. **Laravel Policies** para authorization (ADR-017)
3. **Pest 3** para testes PHP (ADR-008)
4. **TypeScript strict** em todos pacotes JS
5. **Conventional Commits** + DCO sign-off obrigatório
6. **Semver rigoroso** a partir de v1.0
7. **SKILL.md** em cada pacote (estrutura em `PLANNING/00-index.md`)
8. **PT-BR** em documentação, inglês em código

## Onde começar (para um novo agente AI)

1. Ler `CLAUDE.md` para workflow e regras
2. Ler `PLANNING/00-index.md` para navegação do planejamento
3. Ler `PLANNING/03-adrs.md` para entender decisões
4. Ver `docs/tickets/current.md` para ticket ativo
5. Implementar seguindo convenções do código existente

## Referências de planejamento

Não tomar decisões arquiteturais sem consultar:

- `PLANNING/01-spec-tecnica.md` — requisitos (RF-*, RNF-*)
- `PLANNING/03-adrs.md` — 18 decisões canônicas
- `PLANNING/05-api-php.md` — APIs PHP definidas
- `PLANNING/06-api-react.md` — Types TypeScript definidos
- `PLANNING/12-processos-qa.md` — processos e qualidade

## Contato

- Issues: GitHub
- Security: `security@arqel.dev`
- Discussions: GitHub Discussions

---

**Padrão seguido:** [agents.md v1.0](https://agents.md/)
**Última atualização:** 2026-04-17

# SKILL.md — apps/docs

> Contexto canónico para AI agents.

## Purpose

`apps/docs/` é o site público de documentação do Arqel publicado em `arqel.dev` (ou `docs.arqel.dev`). Construído com **VitePress 1.x** — escolha alinhada ao ecossistema Vite, sem o overhead de Next.js, ideal para docs técnicas estáticas com search built-in.

## Status

**Entregue (DOCS-001):**

- Scaffold VitePress 1.6+ com config TS (`.vitepress/config.ts`), theme override (`.vitepress/theme/`), nav + sidebar PT-BR
- Landing page `index.md` (hero + 6 features) com gradient brand indigo→purple
- Stubs PT-BR para todas as 18 páginas referenciadas no sidebar (guide/, resources/, reference/, advanced/, examples/)
- Search local (`provider: 'local'`) com translations PT-BR
- Dark mode auto (default do VitePress)
- Mobile responsive (default)
- Edit-on-GitHub link na footer de cada página
- Assets `public/` (favicon, logo, hero) em SVG inline

**Por chegar (DOCS-002..008):**

- DOCS-002: Getting Started completo (< 10 min walkthrough)
- DOCS-003: Conceitos essenciais (Panels/Resources/Fields/Tables/Forms/Actions/Auth — preenche todos os stubs em `guide/`)
- DOCS-004: Tutorial blog admin (preenche `examples/blog-admin.md`)
- DOCS-005: API reference PHP auto-gerada (phpDocumentor + scripts)
- DOCS-006: API reference TS auto-gerada (TypeDoc + scripts)
- DOCS-007: Migration guides Filament/Nova
- DOCS-008: AGENTS.md template + MCP docs stub
- Algolia DocSearch (substituir search local quando o site tiver tráfego)
- Deploy preview no PR (Cloudflare Pages ou GitHub Pages — decisão em DOCS-001 follow-up)

## Comandos

```bash
cd apps/docs
pnpm dev           # Servidor local em http://localhost:5173
pnpm build         # Build estático em .vitepress/dist
pnpm preview       # Preview do build
pnpm typecheck     # Compila e descarta — valida TypeScript do config + theme
```

## Estrutura

```
apps/docs/
├── .vitepress/
│   ├── config.ts          # Nav, sidebar, theme, search, head meta
│   └── theme/
│       ├── index.ts       # Re-exporta DefaultTheme + carrega style.css
│       └── style.css      # Override de --vp-c-brand-* (paleta indigo/violet)
├── public/                # Assets servidos directamente em /
│   ├── favicon.svg
│   ├── logo.svg
│   └── hero.svg
├── guide/                 # 9 páginas (Getting Started + Conceitos)
├── resources/             # 5 páginas (Resource/Fields/Table/Form/Actions reference)
├── reference/             # 2 páginas (PHP + TS overview)
├── advanced/              # 3 páginas (Custom Fields/Macros/Multi-tenancy)
├── examples/              # 1 página (Blog admin)
├── index.md               # Landing
├── package.json           # Scripts dev/build/preview/typecheck
└── SKILL.md
```

## Conventions

- **PT-BR em todas as páginas markdown** — alinhado com regra "Documentação: português brasileiro" do `CLAUDE.md`
- **Stubs marcam-se com bloco `> **Status:** stub — DOCS-NNN`** — torna visível qual ticket completa cada página
- **Links externos** para SKILL.md no GitHub usam path absoluto (`https://github.com/arqel/arqel/blob/main/...`) — quando o site for autodocumentado em DOCS-005/006, esses links serão substituídos por links internos
- **Assets em `public/`** — paths começam em `/` (ex: `/logo.svg`, `/hero.svg`)
- **Sidebar declarativo no config** — não auto-gera de filesystem; cada nova página precisa de entry explícito em `.vitepress/config.ts`

## Anti-patterns

- ❌ **Adicionar página sem entry no sidebar** — VitePress só lista no nav o que estiver em `themeConfig.sidebar`
- ❌ **Importar `@arqel/*`** — docs site não usa o pacote, só linka para SKILL.md no GitHub
- ❌ **Usar componentes Vue custom em landing antes de DOCS-002** — landing usa o layout `home` default; custom Vue chega em DOCS-008 se necessário
- ❌ **Hardcode de cor em markdown** — temas via CSS vars (`--vp-c-brand-*`) em `theme/style.css`
- ❌ **PR cheio sem search funcional** — verificar `pnpm dev` e abrir o modal de busca antes de mergear

## Related

- Ticket: [`PLANNING/08-fase-1-mvp.md`](../../PLANNING/08-fase-1-mvp.md) §DOCS-001
- VitePress docs: <https://vitepress.dev/>
- Decisões: docs site é P1 (não P0) — bloqueante para o launch público mas não para o MVP funcional

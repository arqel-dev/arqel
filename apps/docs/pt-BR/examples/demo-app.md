# Demo app (showcase)

O `apps/demo` é o app **end-to-end** que demonstra todos os pacotes Arqel
integrados num único projeto: blog admin com **Posts**, **Tags** e
**Categories**, incluindo geração de summary via AI, transições de workflow
(`draft → review → published → archived`), histórico de versões e audit log.

## Por que existe

1. **Showcase público** — rodável em ~2 minutos, base de screenshots e tutoriais.
2. **Smoke test cross-package** — exercita `arqel-dev/core` + `fields` + `workflow`
   + `versioning` + `ai` + `audit` no CI a cada PR.
3. **Boilerplate** — fork-friendly para quem quer começar com Arqel hoje.

## Setup rápido

O demo é **recriado from scratch** a cada execução — não há scaffolding
versionado em `apps/demo/`. O fluxo é o mesmo que um usuário final teria
ao integrar Arqel num app Laravel novo:

```bash
git clone https://github.com/arqel-dev/arqel.git
cd arqel
pnpm install

# Recria o demo do zero usando o instalador one-line.
./apps/demo/scripts/recreate.sh
# internamente:
#   composer create-project laravel/laravel apps/demo
#   composer -d apps/demo require arqel-dev/arqel
#   php artisan arqel:install   # publica configs, tema shadcn (new-york), sidebar e login
#   php artisan migrate --seed --seeder=Database\\Seeders\\DemoSeeder

php -d apps/demo artisan serve
```

Acesse `http://127.0.0.1:8000/admin`.

## UI shadcn (new-york) sobre Radix UI

A migração massiva do framework para shadcn CLI v4 (registry `new-york`)
sobre Radix UI (`radix-ui`) afeta o demo de duas formas visíveis:

- **Login flow shadcn split-screen**: tela `/login` usa o block
  `login-04` (split-screen com painel marketing à esquerda + form à
  direita) — substituiu o card centralizado anterior.
- **Sidebar shadcn `sidebar-07` block**: o admin layout usa o block
  `sidebar-07` (collapsible icon sidebar com header + nav primário +
  footer com user menu) integrado às tokens canônicas (`--background`,
  `--foreground`, `--primary`, `--border`, `--muted`, `--ring`,
  `--radius`, etc.).

## E2E com Playwright

O demo carrega uma suíte Playwright que cobre o fluxo crítico de auth e
o CRUD básico do blog. O ponto de entrada do smoke set fica em:

- `apps/demo/tests-e2e/auth.spec.ts` — login → dashboard → logout.

Rodar localmente:

```bash
pnpm --filter demo exec playwright test
```

No CI, esses testes rodam após o `arqel:install` num runner limpo,
garantindo que a sequência one-line funciona end-to-end.

## Features demonstradas

- Resources declarativos com 7+ tipos de Field (`text`, `slug`, `aiText`,
  `richText`, `stateTransition`, `dateTime`, `belongsTo`).
- Workflow com state machine puro PHP em `app/States/PostStates.php`.
- AI fields offline-friendly (stubs determinísticos quando não há chave).
- 3 usuários, 5 categorias, 20 tags, 50 posts gerados pelo `DemoSeeder`.

## Repositório

- Código: [`apps/demo/`](https://github.com/arqel-dev/arqel/tree/main/apps/demo)
- README: [`apps/demo/README.md`](https://github.com/arqel-dev/arqel/tree/main/apps/demo/README.md)
- SKILL: [`apps/demo/SKILL.md`](https://github.com/arqel-dev/arqel/tree/main/apps/demo/SKILL.md)

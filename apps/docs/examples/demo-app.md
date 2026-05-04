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

```bash
git clone https://github.com/arqel-dev/arqel.git
cd arqel
composer -d apps/demo install
pnpm install
cd apps/demo
touch database/database.sqlite
php artisan migrate --seed --seeder=Database\\Seeders\\DemoSeeder
php artisan serve
```

Acesse `http://127.0.0.1:8000/admin`.

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

# Demo app (showcase)

`apps/demo` es la app **end-to-end** que demuestra cada paquete de Arqel
integrado en un único proyecto: un blog admin con **Posts**, **Tags** y
**Categories**, incluyendo generación de resúmenes con AI, transiciones de workflow
(`draft → review → published → archived`), historial de versiones y un audit log.

## Por qué existe

1. **Showcase público** — ejecutable en ~2 minutos, base para screenshots y tutoriales.
2. **Smoke test cross-package** — ejercita `arqel-dev/core` + `fields` + `workflow`
   + `versioning` + `ai` + `audit` en CI en cada PR.
3. **Boilerplate** — fork-friendly para quien quiera empezar con Arqel hoy.

## Setup rápido

La demo se **recrea desde cero** en cada ejecución — no hay scaffolding versionado
bajo `apps/demo/`. El flujo es el mismo que tendría cualquier usuario final
al integrar Arqel en una app Laravel fresca:

```bash
git clone https://github.com/arqel-dev/arqel.git
cd arqel
pnpm install

# Recreate the demo from zero using the one-line installer.
./apps/demo/scripts/recreate.sh
# under the hood:
#   composer create-project laravel/laravel apps/demo
#   composer -d apps/demo require arqel-dev/framework
#   php artisan arqel:install   # publishes configs, shadcn (new-york) theme, sidebar and login
#   php artisan migrate --seed --seeder=Database\\Seeders\\DemoSeeder

php -d apps/demo artisan serve
```

Abre `http://127.0.0.1:8000/admin`.

## UI shadcn (new-york) sobre Radix UI

La migración masiva del framework a shadcn CLI v4 (registry `new-york`) sobre
Radix UI (`radix-ui`) afecta a la demo en dos formas visibles:

- **Flujo de login split-screen de shadcn**: la página `/login` usa el bloque
  `login-04` (split-screen con panel de marketing a la izquierda + form a la
  derecha) — reemplazando la tarjeta centrada anterior.
- **Sidebar bloque `sidebar-07` de shadcn**: el layout admin usa el bloque
  `sidebar-07` (sidebar con icon collapsible + header + nav primaria +
  footer con menú de usuario) integrada con los tokens canónicos (`--background`,
  `--foreground`, `--primary`, `--border`, `--muted`, `--ring`,
  `--radius`, etc.).

## E2E con Playwright

La demo lleva una suite de Playwright que cubre el flujo crítico de auth y
el CRUD básico del blog. El entry point del smoke set está en:

- `apps/demo/tests-e2e/auth.spec.ts` — login → dashboard → logout.

Ejecutar localmente:

```bash
pnpm --filter demo exec playwright test
```

En CI, estos tests corren después de `arqel:install` en un runner limpio,
garantizando que la secuencia de una sola línea funciona end-to-end.

## Features demostradas

- Resources declarativos con 7+ tipos de Field (`text`, `slug`, `aiText`,
  `richText`, `stateTransition`, `dateTime`, `belongsTo`).
- Workflow con state machine pure-PHP en `app/States/PostStates.php`.
- Fields AI offline-friendly (stubs deterministas cuando no hay key configurada).
- 3 usuarios, 5 categorías, 20 tags, 50 posts generados por `DemoSeeder`.

## Repositorio

- Código: [`apps/demo/`](https://github.com/arqel-dev/arqel/tree/main/apps/demo)
- README: [`apps/demo/README.md`](https://github.com/arqel-dev/arqel/tree/main/apps/demo/README.md)
- SKILL: [`apps/demo/SKILL.md`](https://github.com/arqel-dev/arqel/tree/main/apps/demo/SKILL.md)

# Contribuir a Arqel

¡Bienvenido! Arqel es un framework open-source MIT mantenido por la comunidad. Esta guía explica en detalle cómo empezar a contribuir, desde el primer `git clone` hasta el primer PR aprobado.

> Resumen ejecutivo en el [`CONTRIBUTING.md`](https://github.com/arqel-dev/arqel/blob/main/CONTRIBUTING.md) raíz. Este documento expande con más contexto, ejemplos y gotchas.

## Por qué contribuir

Arqel existe para hacer que los admin panels Laravel + React sean tan productivos como Filament y Nova, pero con un stack moderno (React 19.2, Inertia 3, TypeScript strict, Radix UI). Cada contribución:

- **Acelera el ecosistema Laravel** ofreciendo una alternativa first-class a Filament/Nova
- **Reduce deuda técnica** en proyectos que dependen de admin panels
- **Construye reputación** — los autores son acreditados en las release notes y pueden volverse maintainers
- **Hay mucho que aprender** — el repo combina PHP moderno, React 19.2, Inertia, monorepo, CI matrix, splitsh y patrones de diseño de framework

El tamaño de la contribución no importa: arreglar un typo, un Field nuevo, una vertical completa — todo es bienvenido siempre que siga los estándares de este documento.

## Antes de empezar

Lee, en orden:

1. [`README.md`](https://github.com/arqel-dev/arqel/blob/main/README.md) — overview del proyecto.
2. [`CLAUDE.md`](https://github.com/arqel-dev/arqel/blob/main/CLAUDE.md) — convenciones operacionales (idioma, stack, commits).
3. [`PLANNING/00-index.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/00-index.md) — estructura del plan.
4. [`PLANNING/03-adrs.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md) — 18 ADRs canónicos. **No contradigas sin un RFC.**
5. [`CODE_OF_CONDUCT.md`](https://github.com/arqel-dev/arqel/blob/main/CODE_OF_CONDUCT.md).

Si tu contribución es una feature nueva grande, **abre primero una discussion** en [GitHub Discussions](https://github.com/arqel-dev/arqel/discussions) o un issue con el label `rfc`. Esto evita retrabajo.

## Requisitos previos

| Herramienta | Versión mínima | Notas |
|---|---|---|
| PHP | 8.3 | Testeado en 8.3 y 8.4. PHPStan level max. |
| Composer | 2.x | — |
| Node | 20.9 LTS | v22 recomendado (`.nvmrc`). |
| pnpm | 10+ | `corepack enable pnpm`. |
| Git | 2.30+ | Para `--signoff` y worktrees. |

Extensiones PHP requeridas: `mbstring`, `intl`, `pdo_mysql`, `pdo_pgsql`, `redis`, `zip`, `bcmath`.

## Setup completo (paso a paso)

### 1. Fork + clone

```bash
# En GitHub: arqel-dev/arqel → Fork
git clone https://github.com/<your-user>/arqel.git
cd arqel
git remote add upstream https://github.com/arqel-dev/arqel.git
```

### 2. Selecciona la versión de Node

```bash
nvm use   # lee .nvmrc
corepack enable pnpm
```

### 3. Instala dependencias

```bash
./scripts/init.sh
```

El script:

- `composer install` en la raíz y en cada `packages/*` que tenga `composer.json`.
- `pnpm install` en la raíz (workspaces).
- Configura los hooks de Husky (`commit-msg`, `pre-commit`).
- Corre smoke tests para validar que el setup funciona.

### 4. Verifica el setup

```bash
pnpm run lint        # Biome en JS/TS
pnpm run typecheck   # tsc --noEmit en cada workspace
pnpm run test        # Vitest

vendor/bin/pint --test         # Pint sin aplicar
vendor/bin/phpstan analyse     # Level max
vendor/bin/pest                # Pest 3
```

Si algún comando falla antes de hacer cambios, abre un issue — el setup debería estar limpio en `main`.

## Workflow de PR

### 1. Crea una rama

Convención de nombres: `<type>/<scope>-<short-description>`.

```bash
git checkout -b feat/fields-add-color-picker
git checkout -b fix/table-pagination-edge-case
git checkout -b docs/guide-update-realtime-section
```

Tipos válidos (alineados con Conventional Commits): `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `chore`, `ci`, `build`, `style`.

### 2. Implementa el cambio

- **Tests primero** cuando sea posible (Pest para PHP, Vitest para JS).
- Mantén la coverage: ≥90% para core PHP, ≥80% para core JS.
- Actualiza el `SKILL.md` del paquete si la API pública cambia.
- Actualiza `apps/docs/` si hay un cambio visible para el usuario.

### 3. Corre el checklist local

```bash
pnpm test:all                  # lint + typecheck + tests, todo
vendor/bin/pint                # aplica Pint
vendor/bin/phpstan analyse     # level max
vendor/bin/pest --coverage     # con coverage
```

### 4. Commit con Conventional Commits + DCO

**El DCO sign-off es obligatorio** — sin él, el PR es rechazado por el bot.

```bash
git commit --signoff -m "feat(fields): add ColorField with preset palette

Implements FIELDS-042 from PLANNING/08-fase-1-mvp.md.

- Supports custom palette via the palette prop
- Clickable preview opens BasePicker
- Test coverage: 95%
"
```

Formato:

```
<type>(<scope>): <description>

[body opcional explicando el "porqué"]

[footer con referencia al ticket: Implements FOO-001]
```

Scopes comunes: nombre del paquete (`core`, `fields`, `table`, `marketplace`, `ai`, `realtime`, `ui`, `react`, `docs`, `ci`).

### 5. Sincroniza con upstream

```bash
git fetch upstream
git rebase upstream/master
```

Usa rebase, no merge — mantiene el historial lineal.

### 6. Abre el PR

- Título en formato Conventional Commits.
- Rellena la plantilla `.github/PULL_REQUEST_TEMPLATE.md`.
- Marca "Allow edits from maintainers".
- Vincula el issue o ticket relacionado.
- Si hay UI, adjunta screenshots o GIFs.

### 7. Code review

- Al menos **1 maintainer** debe aprobar.
- El CI debe pasar (matrix PHP × Laravel, lint, typecheck).
- Resuelve todos los comentarios antes del merge.
- Si el PR queda >7 días, comenta haciendo ping a los maintainers.

### 8. Merge

Los maintainers hacen squash merge para mantener el historial limpio. El mensaje final sigue el título del PR.

## Guía de estilo

### PHP

- `declare(strict_types=1);` en cada archivo.
- Clases `final` por defecto. Usa `abstract` o `extends` solo cuando la extensibilidad sea design intent.
- Usa features nativas de Laravel (Policy, FormRequest, Eloquent, Gate) antes de reinventar.
- Respeta `pint.json` (preset Laravel + tweaks del proyecto).
- PHPStan level max — sin `mixed` sin necesidad.

### TypeScript / React

- `strict: true` + `noUncheckedIndexedAccess: true` (ya en `tsconfig.base.json`).
- Componentes funcionales siempre. Sin class components.
- Hooks: prefijo `use`, reglas de React en strict mode.
- Tipos exportados en `@arqel-dev/types`. Nunca duplicar entre paquetes.
- ESLint vía Biome (`biome.json`).

### Inertia-only (ADR-001)

El único bridge PHP↔React es Inertia 3. **No añadas** TanStack Query, SWR, Axios, wrappers fetch para CRUD de Resource. Las props de Inertia son el estado por defecto.

### Documentación

- Español (estándar). Modismos en español neutro bienvenidos.
- Código en inglés (nombres de clases, variables, comentarios inline).
- Ejemplos completos y ejecutables siempre que sea posible.

## Cómo añadir un nuevo paquete

1. Pon la estructura en `packages/<name>/` (PHP) o `packages-js/<name>/` (JS).
2. Añade `composer.json` o `package.json` siguiendo el patrón de los paquetes existentes.
3. Crea `SKILL.md` con la estructura canónica (`PLANNING/00-index.md` §5):
   - Purpose, Key Contracts, Conventions, Examples, Anti-patterns, Related.
4. Añade tests (`tests/` PHP + `*.test.ts` JS).
5. Actualiza:
   - `pnpm-workspace.yaml` (si JS).
   - `repositories` del `composer.json` raíz (si PHP, path repo).
   - `apps/docs/.vitepress/config.ts` si es visible en las docs.
   - `.github/labeler.yml` añadiendo una regla para el nuevo paquete.
   - `CODEOWNERS` añadiendo la línea apropiada.
6. Abre un PR con el label `new-package`.

## Cómo proponer un nuevo ticket en PLANNING

Los tickets viven en `PLANNING/08-*.md` (Fase 1) hasta `PLANNING/11-*.md` (Fase 4). Para proponer:

1. Abre un issue con el label `proposal-ticket` describiendo: contexto, problema, propuesta de API, criterios de aceptación.
2. Discusión en Discussions o en el issue.
3. Tras la aprobación, un maintainer añade el ticket al archivo correcto siguiendo la plantilla:

```markdown
### [PACKAGE-###] Title

**Type:** feat • **Priority:** P0-P3 • **Estimate:** XS-XL • **Layer:** php|react|shared|infra|docs • **Depends on:** [OTHER-TICKET]

**Context** (why it exists)
**Technical description** (what to do + example code)
**Acceptance criteria** (checkboxes)
**Implementation notes** (gotchas)
```

## Corre diagnósticos antes del PR

Dos comandos útiles (disponibles tras Fase 1):

```bash
php artisan arqel:doctor    # Chequea versiones, configs, integridad del panel
php artisan arqel:audit     # Audita Resources/Fields contra los ADRs
```

Adjunta el output al PR si el cambio toca la integración entre paquetes.

## Dónde discutir antes del PR

- **GitHub Discussions** — preguntas, RFCs informales, brainstorming.
- **Issues con label `rfc`** — RFCs formales para cambios de API.
- **Discord** (link en el README cuando esté disponible) — chat rápido.

## Gotchas comunes

- **DCO olvidado**: rebase con `git rebase --signoff -i HEAD~N` para añadir sign-offs retroactivamente.
- **PHPStan max fallando en código nuevo**: PHPStan ahora es strict; valida con `vendor/bin/phpstan analyse` antes de pushear.
- **Biome quejándose en archivos no tocados**: corre `pnpm run lint:fix` solo en tus archivos con `--files-ignore-unknown=true` o explícitamente.
- **Path repos de Composer no actualizándose**: corre `composer update arqel/*` en la raíz para traer cambios locales.
- **Husky no corriendo los hooks**: confirma que el setup corrió `pnpm run prepare` (instala hooks).
- **Tests de matrix PostgreSQL fallando localmente**: el CI usa un service dedicado; localmente prefiere MySQL o corre `docker compose up postgres` si hay un `compose.yml`.

## Reconocimiento

Los contribuidores se listan automáticamente vía [all-contributors](https://allcontributors.org/) (se habilitará antes de `v1.0`). Los maintainers activos ganan acceso de triage y merge tras 5+ PRs aprobados o una invitación explícita.

## Soporte y preguntas

- Bug o comportamiento inesperado: [issue con la plantilla `bug_report`](https://github.com/arqel-dev/arqel/issues/new?template=bug_report.yml).
- Pregunta de uso: [Discussions](https://github.com/arqel-dev/arqel/discussions) o la plantilla `question`.
- Vulnerabilidad de seguridad: **NO** abras un issue público — sigue [`SECURITY.md`](https://github.com/arqel-dev/arqel/blob/main/SECURITY.md).

¡Gracias por contribuir a Arqel!

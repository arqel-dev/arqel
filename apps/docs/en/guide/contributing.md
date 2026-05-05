# Contributing to Arqel

Welcome! Arqel is an MIT open-source framework maintained by the community. This guide explains in detail how to start contributing, from the first `git clone` to the first approved PR.

> Executive summary in the root [`CONTRIBUTING.md`](https://github.com/arqel-dev/arqel/blob/main/CONTRIBUTING.md). This document expands with more context, examples, and gotchas.

## Why contribute

Arqel exists to make Laravel + React admin panels as productive as Filament and Nova, but with a modern stack (React 19.2, Inertia 3, strict TypeScript, Radix UI). Each contribution:

- **Speeds up the Laravel ecosystem** by offering a first-class alternative to Filament/Nova
- **Reduces tech debt** in projects that depend on admin panels
- **Builds reputation** — authors are credited in release notes and can become maintainers
- **Lots to learn** — the repo combines modern PHP, React 19.2, Inertia, monorepo, CI matrix, splitsh, and framework design patterns

The size of the contribution doesn't matter: typo fix, new Field, full vertical — everything is welcome as long as it follows the standards in this document.

## Before you start

Read, in order:

1. [`README.md`](https://github.com/arqel-dev/arqel/blob/main/README.md) — project overview.
2. [`CLAUDE.md`](https://github.com/arqel-dev/arqel/blob/main/CLAUDE.md) — operational conventions (language, stack, commits).
3. [`PLANNING/00-index.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/00-index.md) — plan structure.
4. [`PLANNING/03-adrs.md`](https://github.com/arqel-dev/arqel/blob/main/PLANNING/03-adrs.md) — 18 canonical ADRs. **Don't contradict without an RFC.**
5. [`CODE_OF_CONDUCT.md`](https://github.com/arqel-dev/arqel/blob/main/CODE_OF_CONDUCT.md).

If your contribution is a large new feature, **open a discussion first** in [GitHub Discussions](https://github.com/arqel-dev/arqel/discussions) or an issue with the `rfc` label. This avoids rework.

## Prerequisites

| Tool | Minimum version | Notes |
|---|---|---|
| PHP | 8.3 | Tested on 8.3 and 8.4. PHPStan level max. |
| Composer | 2.x | — |
| Node | 20.9 LTS | v22 recommended (`.nvmrc`). |
| pnpm | 10+ | `corepack enable pnpm`. |
| Git | 2.30+ | For `--signoff` and worktrees. |

Required PHP extensions: `mbstring`, `intl`, `pdo_mysql`, `pdo_pgsql`, `redis`, `zip`, `bcmath`.

## Full setup (step by step)

### 1. Fork + clone

```bash
# On GitHub: arqel-dev/arqel → Fork
git clone https://github.com/<your-user>/arqel.git
cd arqel
git remote add upstream https://github.com/arqel-dev/arqel.git
```

### 2. Select Node version

```bash
nvm use   # reads .nvmrc
corepack enable pnpm
```

### 3. Install dependencies

```bash
./scripts/init.sh
```

The script:

- `composer install` at the root and in each `packages/*` that has a `composer.json`.
- `pnpm install` at the root (workspaces).
- Sets up Husky hooks (`commit-msg`, `pre-commit`).
- Runs smoke tests to validate the setup works.

### 4. Verify the setup

```bash
pnpm run lint        # Biome on JS/TS
pnpm run typecheck   # tsc --noEmit on each workspace
pnpm run test        # Vitest

vendor/bin/pint --test         # Pint without applying
vendor/bin/phpstan analyse     # Level max
vendor/bin/pest                # Pest 3
```

If any command fails before you make changes, open an issue — the setup should be clean on `main`.

## PR workflow

### 1. Create a branch

Naming convention: `<type>/<scope>-<short-description>`.

```bash
git checkout -b feat/fields-add-color-picker
git checkout -b fix/table-pagination-edge-case
git checkout -b docs/guide-update-realtime-section
```

Valid types (aligned with Conventional Commits): `feat`, `fix`, `docs`, `refactor`, `perf`, `test`, `chore`, `ci`, `build`, `style`.

### 2. Implement the change

- **Tests first** when possible (Pest for PHP, Vitest for JS).
- Maintain coverage: ≥90% for core PHP packages, ≥80% for core JS.
- Update the package's `SKILL.md` if the public API changes.
- Update `apps/docs/` if there's a user-visible change.

### 3. Run the local checklist

```bash
pnpm test:all                  # lint + typecheck + tests, all of it
vendor/bin/pint                # apply Pint
vendor/bin/phpstan analyse     # level max
vendor/bin/pest --coverage     # with coverage
```

### 4. Commit with Conventional Commits + DCO

**DCO sign-off is required** — without it the PR is rejected by the bot.

```bash
git commit --signoff -m "feat(fields): add ColorField with preset palette

Implements FIELDS-042 from PLANNING/08-fase-1-mvp.md.

- Supports custom palette via the palette prop
- Clickable preview opens BasePicker
- Test coverage: 95%
"
```

Format:

```
<type>(<scope>): <description>

[optional body explaining the "why"]

[footer with ticket reference: Implements FOO-001]
```

Common scopes: package name (`core`, `fields`, `table`, `marketplace`, `ai`, `realtime`, `ui`, `react`, `docs`, `ci`).

### 5. Sync with upstream

```bash
git fetch upstream
git rebase upstream/master
```

Use rebase, not merge — keeps history linear.

### 6. Open the PR

- Title in Conventional Commits format.
- Fill in the `.github/PULL_REQUEST_TEMPLATE.md` template.
- Check "Allow edits from maintainers".
- Link the related issue or ticket.
- If there's UI, attach screenshots or GIFs.

### 7. Code review

- At least **1 maintainer** must approve.
- CI must pass (PHP × Laravel matrix, lint, typecheck).
- Resolve all comments before merge.
- If the PR sits >7 days, comment pinging maintainers.

### 8. Merge

Maintainers squash merge to keep history clean. The final message follows the PR title.

## Style guide

### PHP

- `declare(strict_types=1);` in every file.
- Classes `final` by default. Use `abstract` or `extends` only when extensibility is design intent.
- Use Laravel-native features (Policy, FormRequest, Eloquent, Gate) before reinventing.
- Respect `pint.json` (Laravel preset + project tweaks).
- PHPStan level max — no `mixed` without need.

### TypeScript / React

- `strict: true` + `noUncheckedIndexedAccess: true` (already in `tsconfig.base.json`).
- Functional components always. No class components.
- Hooks: `use` prefix, React rules in strict mode.
- Types exported in `@arqel-dev/types`. Never duplicate across packages.
- ESLint via Biome (`biome.json`).

### Inertia-only (ADR-001)

The only PHP↔React bridge is Inertia 3. **Do not add** TanStack Query, SWR, Axios, fetch wrappers for Resource CRUD. Inertia props are the default state.

### Documentation

- English (US/standard). Native English idioms welcome.
- Code in English (class names, variables, inline comments).
- Complete and runnable examples whenever possible.

## How to add a new package

1. Drop the structure into `packages/<name>/` (PHP) or `packages-js/<name>/` (JS).
2. Add `composer.json` or `package.json` following the pattern of existing packages.
3. Create `SKILL.md` with the canonical structure (`PLANNING/00-index.md` §5):
   - Purpose, Key Contracts, Conventions, Examples, Anti-patterns, Related.
4. Add tests (`tests/` PHP + `*.test.ts` JS).
5. Update:
   - `pnpm-workspace.yaml` (if JS).
   - Root `composer.json` `repositories` (if PHP, path repo).
   - `apps/docs/.vitepress/config.ts` if visible in the docs.
   - `.github/labeler.yml` adding a rule for the new package.
   - `CODEOWNERS` adding the appropriate line.
6. Open a PR with the `new-package` label.

## How to propose a new ticket in PLANNING

Tickets live in `PLANNING/08-*.md` (Phase 1) through `PLANNING/11-*.md` (Phase 4). To propose:

1. Open an issue with the `proposal-ticket` label describing: context, problem, API proposal, acceptance criteria.
2. Discussion in Discussions or in the issue.
3. After approval, a maintainer adds the ticket to the correct file following the template:

```markdown
### [PACKAGE-###] Title

**Type:** feat • **Priority:** P0-P3 • **Estimate:** XS-XL • **Layer:** php|react|shared|infra|docs • **Depends on:** [OTHER-TICKET]

**Context** (why it exists)
**Technical description** (what to do + example code)
**Acceptance criteria** (checkboxes)
**Implementation notes** (gotchas)
```

## Run diagnostics before the PR

Two useful commands (available after Phase 1):

```bash
php artisan arqel:doctor    # Checks versions, configs, panel integrity
php artisan arqel:audit     # Audits Resources/Fields against ADRs
```

Attach the output to the PR if the change touches integration between packages.

## Where to discuss before the PR

- **GitHub Discussions** — questions, informal RFCs, brainstorming.
- **Issues with `rfc` label** — formal RFCs for API changes.
- **Discord** (link in README when available) — quick chat.

## Common gotchas

- **Forgotten DCO**: rebase with `git rebase --signoff -i HEAD~N` to retroactively add sign-offs.
- **PHPStan max failing on new code**: PHPStan is now strict; validate with `vendor/bin/phpstan analyse` before pushing.
- **Biome complaining on untouched files**: run `pnpm run lint:fix` only on your files with `--files-ignore-unknown=true` or explicitly.
- **Composer path repos not updating**: run `composer update arqel/*` at the root to pull local changes.
- **Husky not running hooks**: confirm setup ran `pnpm run prepare` (installs hooks).
- **PostgreSQL matrix tests failing locally**: CI uses a dedicated service; locally prefer MySQL or run `docker compose up postgres` if there's a `compose.yml`.

## Recognition

Contributors are listed automatically via [all-contributors](https://allcontributors.org/) (will be enabled before `v1.0`). Active maintainers gain triage and merge access after 5+ approved PRs or an explicit invitation.

## Support and questions

- Bug or unexpected behavior: [issue with the `bug_report` template](https://github.com/arqel-dev/arqel/issues/new?template=bug_report.yml).
- Usage question: [Discussions](https://github.com/arqel-dev/arqel/discussions) or the `question` template.
- Security vulnerability: **DO NOT** open a public issue — follow [`SECURITY.md`](https://github.com/arqel-dev/arqel/blob/main/SECURITY.md).

Thanks for contributing to Arqel!

# Fresh Laravel project end-to-end validation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Validate the Arqel framework end-to-end after the shadcn migration by (a) finalizing the partial `arqel-dev/arqel` → `arqel-dev/framework` rename, (b) recreating `apps/demo/` from a fresh Laravel 12 skeleton with a full PostResource showcase, and (c) smoke-testing the real user installation flow via Packagist in an external project.

**Architecture:** Three sequential phases with explicit checkpoints. Phase 1 is mechanical refactor (rename + commit). Phase 2 is integration test via path repos inside the monorepo. Phase 3 is the user-facing smoke test via Packagist outside the monorepo. Each phase has independent acceptance criteria; failures roll back to the affected phase, never cascade.

**Tech Stack:** Laravel 12 + Inertia 3 + React 19.2 + Vite 7 + Tailwind v4 + shadcn (new-york) + Pest 3 + Vitest 3 + pnpm workspaces + Composer path repositories.

**Spec:** `docs/superpowers/specs/2026-05-06-fresh-laravel-e2e-validation-design.md` (commit `a53ddd3`).

---

## Repo conventions reminder

Read these before starting any task:

- **Commit format:** Conventional Commits with `--signoff` (DCO). Co-Authored-By Claude trailer required. Allowed scopes (commitlint-enforced): `core, panel, fields, table, form, actions, auth, cli, nav, tenant, ai, widgets, fields-advanced, audit, export, marketplace, versioning, workflow, realtime, mcp, testing, react, ui, hooks, types, devtools, infra, lcloud, gov, docs, demo, qa, tickets, sprint, deps, release`. **`spec` is NOT a valid scope** — use `docs` for spec files.
- **No `cd && git`:** use `git -C <path> ...`. No compound `cd && shell-redir`. See `CLAUDE.md` "Convenções de comandos shell".
- **Pre-commit hooks:** Husky runs lint-staged with biome check. Lint-staged includes ALL staged untracked files via `package.json` glob — be careful what you `git add`. Always stage explicit paths, never `git add -A`/`git add .`.
- **Never `--no-verify`** unless user authorizes.
- **PHP files:** `declare(strict_types=1)` mandatory; classes `final` by default.
- **Pre-existing dirty working tree** (as of 2026-05-06 commit `a53ddd3`): `CLAUDE.md`, `PLANNING/07-roadmap-fases.md`, `pnpm-lock.yaml`, `scripts/trigger-packagist-update.sh` modified; `PLANNING/13-pos-mvp-mcp-server.md` and `packages-js/mcp-server/` untracked. **These are NOT yours**. Do not stage them. Use explicit paths in `git add`.

---

# PHASE 1 — Naming saneamento

## Task 1: Audit & inventory the drift

**Files:**
- Create: `docs/superpowers/plans/phase1-audit.txt` (transient, gitignored — used only as worksheet)

- [ ] **Step 1: Capture full audit list grouped by context**

```bash
cd /home/diogo/PhpstormProjects/arqel
{
  echo "=== A) composer require contexts (BUGS) ==="
  grep -rn "arqel-dev/arqel" --include="*.json" --include="*.php" --include="*.md" --include="*.ts" --include="*.tsx" --include="*.yml" --include="*.yaml" \
    -- . ':!node_modules' ':!vendor' ':!docs/superpowers/specs' \
    | grep -E '("arqel-dev/arqel"\s*:|composer require arqel-dev/arqel\b|composer -d [^ ]+ require arqel-dev/arqel\b|composer require .arqel-dev/arqel:|`arqel-dev/arqel` (is|é) the?\s*\*\*meta-package|arqel-dev/arqel.* meta-package|Meta-package .arqel-dev/arqel)'
  echo ""
  echo "=== B) GitHub URL contexts (KEEP) ==="
  grep -rn "arqel-dev/arqel" --include="*.json" --include="*.md" --include="*.yml" \
    -- . ':!node_modules' ':!vendor' \
    | grep -E 'github\.com/arqel-dev/arqel|arqel-dev/arqel\.git|arqel-dev/arqel/(discussions|security|issues|actions|compare|pull|releases|wiki|tags)'
  echo ""
  echo "=== C) packages/arqel/composer.json name field ==="
  grep -n '"name"' /home/diogo/PhpstormProjects/arqel/packages/arqel/composer.json
  echo ""
  echo "=== D) Ambiguous (manual review) ==="
  grep -rn "arqel-dev/arqel" --include="*.php" --include="*.md" --include="*.json" --include="*.ts" \
    -- . ':!node_modules' ':!vendor' ':!docs/superpowers' \
    | grep -vE 'github\.com/arqel-dev/arqel|arqel-dev/arqel\.git|arqel-dev/arqel/(discussions|security|issues|actions|compare|pull|releases|wiki|tags)' \
    | grep -vE '"arqel-dev/arqel"\s*:|composer require arqel-dev/arqel\b|composer -d [^ ]+ require arqel-dev/arqel\b|`arqel-dev/arqel` (is|é) the?\s*\*\*meta-package|arqel-dev/arqel.* meta-package|Meta-package .arqel-dev/arqel'
} > docs/superpowers/plans/phase1-audit.txt
wc -l docs/superpowers/plans/phase1-audit.txt
```

Expected: `phase1-audit.txt` with grouped sections. Sections A, C should be small (~10 lines). Section B large (URLs are everywhere). Section D is the manual review pile — should also be small if our heuristics are right.

- [ ] **Step 2: Add audit file to .gitignore so it does not leak into commits**

Edit `.gitignore`, append at the end:

```
# Phase-1 transient audit worksheets
/docs/superpowers/plans/phase1-audit.txt
/docs/superpowers/plans/phase1-*.tmp
```

- [ ] **Step 3: Verify Section D (ambiguous) is empty or trivially classifiable**

```bash
sed -n '/=== D)/,$p' docs/superpowers/plans/phase1-audit.txt | head -50
```

If Section D has more than ~5 lines, **STOP** and surface to user — heuristics may need refinement. If trivially classifiable (e.g., obvious doc text or comments), proceed.

- [ ] **Step 4: No commit yet** — audit is exploratory only.

---

## Task 2: Fix structural files (Pass 1 — manual)

**Files:**
- Modify: `packages/arqel/composer.json` — `name` field
- Modify: `apps/demo/composer.json` — `require` field (line 10)
- Modify: `package.json` (root) if it references `arqel-dev/arqel` outside repository URL
- Modify: `.github/workflows/release.yml`
- Modify: `.github/workflows/docs-deploy.yml`
- Modify: any `composer.json` under `packages/*/` that references `arqel-dev/arqel` in `require` or `require-dev`

- [ ] **Step 1: Read packages/arqel/composer.json and verify current state**

```bash
grep -n '"name"' /home/diogo/PhpstormProjects/arqel/packages/arqel/composer.json
```

Expected current: `2:    "name": "arqel-dev/arqel",`

- [ ] **Step 2: Edit packages/arqel/composer.json — change name field**

Use Edit tool:
- file: `/home/diogo/PhpstormProjects/arqel/packages/arqel/composer.json`
- old_string: `    "name": "arqel-dev/arqel",`
- new_string: `    "name": "arqel-dev/framework",`

Note: leave `support.issues`, `support.source`, `authors[].homepage` (all `github.com/arqel-dev/arqel`) UNCHANGED — these are GitHub URLs.

- [ ] **Step 3: Edit apps/demo/composer.json — change require key**

```bash
grep -n 'arqel-dev/arqel' /home/diogo/PhpstormProjects/arqel/apps/demo/composer.json
```

Expected: `10:    "arqel-dev/arqel": "@dev",`

Use Edit tool:
- file: `/home/diogo/PhpstormProjects/arqel/apps/demo/composer.json`
- old_string: `    "arqel-dev/arqel": "@dev",`
- new_string: `    "arqel-dev/framework": "@dev",`

- [ ] **Step 4: Audit & fix all sub-package composer.json**

```bash
grep -rn '"arqel-dev/arqel"' /home/diogo/PhpstormProjects/arqel/packages/*/composer.json
```

For each hit (other than `packages/arqel/composer.json` which is already fixed): use Edit to replace the dependency key. Each will look like `"arqel-dev/arqel": "self.version"` or `"arqel-dev/arqel": "^0.8"` — verify it is referencing the meta-package (suspicious in a sub-package; might actually be intentional somewhere). If a sub-package depends on the meta-package, that is a circular dep — flag and stop.

Expected: zero hits (sub-packages depend on `arqel-dev/core` etc., not on the meta).

- [ ] **Step 5: Audit & fix all package.json**

```bash
grep -rn '"arqel-dev/arqel"' /home/diogo/PhpstormProjects/arqel/package.json /home/diogo/PhpstormProjects/arqel/packages-js/*/package.json /home/diogo/PhpstormProjects/arqel/apps/*/package.json
```

For each hit, classify:
- If it's `"repository": { "url": ".../arqel-dev/arqel.git" }` → KEEP (GitHub URL)
- If it's a dependency entry → fix with Edit

Expected: only repository URLs hit; no fixes needed.

- [ ] **Step 6: Fix CI workflow files**

```bash
grep -n 'arqel-dev/arqel' /home/diogo/PhpstormProjects/arqel/.github/workflows/*.yml /home/diogo/PhpstormProjects/arqel/.github/ISSUE_TEMPLATE/*.yml
```

For each hit, classify by surrounding context:
- `composer require arqel-dev/arqel` → fix with Edit (replace with `arqel-dev/framework`)
- `actions/checkout` repo refs / clone URLs / `gh repo` flags → KEEP
- Issue template link to `arqel-dev/arqel/issues` → KEEP

- [ ] **Step 7: Verify no PHP/TS source code references**

```bash
grep -rn '"arqel-dev/arqel"' /home/diogo/PhpstormProjects/arqel/packages/*/src /home/diogo/PhpstormProjects/arqel/packages-js/*/src 2>/dev/null
grep -rn "'arqel-dev/arqel'" /home/diogo/PhpstormProjects/arqel/packages/*/src /home/diogo/PhpstormProjects/arqel/packages-js/*/src 2>/dev/null
```

Expected: empty.

- [ ] **Step 8: Run composer validate on the meta-package**

```bash
cd /home/diogo/PhpstormProjects/arqel/packages/arqel && composer validate
```

Expected: `./composer.json is valid`. Validates that the `name` change is well-formed JSON and Composer-acceptable.

- [ ] **Step 9: Run composer validate on apps/demo**

```bash
composer -d /home/diogo/PhpstormProjects/arqel/apps/demo validate
```

Expected: `./composer.json is valid`. NOTE: this does NOT install — just lints.

- [ ] **Step 10: No commit yet** — Pass 1, 2, 3 commit together at end of Task 4.

---

## Task 3: Fix code references (Pass 2 — manual)

**Files:**
- Modify: `packages/cli/src/Generators/SetupScriptGenerator.php`
- Modify: `packages/cli/src/Commands/NewCommand.php`
- Modify: `packages/cli/tests/Unit/ScriptGeneratorTest.php`
- Modify: `packages/cli/tests/Feature/NewCommandTest.php`
- Modify: `packages/core/src/Console/DoctorCommand.php` (if drift detected)

- [ ] **Step 1: Read SetupScriptGenerator.php to understand the comment context**

```bash
grep -n 'arqel-dev/arqel' /home/diogo/PhpstormProjects/arqel/packages/cli/src/Generators/SetupScriptGenerator.php
```

Expected: line 33 `* Packages required when expanding `arqel-dev/arqel` against a local monorepo.`

This is a **PHPDoc comment** describing the meta-package. Fix.

- [ ] **Step 2: Fix the comment**

Use Edit tool:
- file: `/home/diogo/PhpstormProjects/arqel/packages/cli/src/Generators/SetupScriptGenerator.php`
- old_string: `* Packages required when expanding `arqel-dev/arqel` against a local monorepo.`
- new_string: `* Packages required when expanding `arqel-dev/framework` against a local monorepo.`

- [ ] **Step 3: Audit other CLI references**

```bash
grep -n 'arqel-dev/arqel' /home/diogo/PhpstormProjects/arqel/packages/cli/src/Commands/NewCommand.php /home/diogo/PhpstormProjects/arqel/packages/cli/tests/**/*.php 2>/dev/null
```

For each hit, read context with Read tool (3-5 lines surrounding) and decide:
- Comment / string referring to the meta-package → fix
- URL → keep
- Path repo URL `path:../../packages/arqel` → KEEP (this is the directory name, not the package name)

- [ ] **Step 4: Audit DoctorCommand.php**

```bash
grep -n 'arqel-dev/arqel\|arqel-dev/framework' /home/diogo/PhpstormProjects/arqel/packages/core/src/Console/DoctorCommand.php
```

If references `arqel-dev/arqel` (e.g., in a doctor check `Composer\InstalledVersions::getVersion('arqel-dev/arqel')`), fix it. If references `arqel-dev/framework`, leave it.

- [ ] **Step 5: Run cli tests to verify rename did not break anything**

```bash
cd /home/diogo/PhpstormProjects/arqel/packages/cli && vendor/bin/pest --filter='Generator|NewCommand' 2>&1 | tail -20
```

If `vendor/bin/pest` is missing, run `composer install` first inside `packages/cli/`. Expected: tests green.

- [ ] **Step 6: Run core tests for DoctorCommand if modified**

```bash
cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest --filter='Doctor' 2>&1 | tail -20
```

Expected: tests green. If a test asserts on the old string, update the assertion.

- [ ] **Step 7: No commit yet** — wait for Pass 3.

---

## Task 4: Mass-rename docs (Pass 3 — sed defensive)

**Files:**
- Modify: ~120 markdown files under `apps/docs/`, `PLANNING/`, `README*.md`, `CHANGELOG.md`, `CONTRIBUTING.md`, `AGENTS.md`, `SECURITY.md`, `packages/*/SKILL.md`, `packages/*/README.md`, `packages-js/*/SKILL.md`, `packages-js/*/README.md`, `docs/*.md`

- [ ] **Step 1: Dry-run the sed to preview changes (no in-place modification)**

```bash
cd /home/diogo/PhpstormProjects/arqel
find . \( -name "*.md" -o -name "*.yml" -o -name "*.yaml" \) \
  -not -path "./node_modules/*" -not -path "./vendor/*" -not -path "./apps/demo/node_modules/*" -not -path "./apps/demo/vendor/*" \
  -not -path "./docs/superpowers/specs/*" \
  -print0 \
| xargs -0 grep -l 'arqel-dev/arqel' 2>/dev/null \
| xargs -I {} sh -c '
    diff -u "{}" <(sed \
        -e "s|composer require arqel-dev/arqel\b|composer require arqel-dev/framework|g" \
        -e "s|composer -d \\([^ ][^ ]*\\) require arqel-dev/arqel\b|composer -d \\1 require arqel-dev/framework|g" \
        -e "s|'\''arqel-dev/arqel:|'\''arqel-dev/framework:|g" \
        -e "s|\"arqel-dev/arqel\":|\"arqel-dev/framework\":|g" \
        -e "s|\\\\\`arqel-dev/arqel\\\\\` é o \\*\\*meta-package\\*\\*|\\\\\`arqel-dev/framework\\\\\` é o **meta-package**|g" \
        -e "s|\\\\\`arqel-dev/arqel\\\\\` is the \\*\\*meta-package\\*\\*|\\\\\`arqel-dev/framework\\\\\` is the **meta-package**|g" \
        -e "s|\\\\\`arqel-dev/arqel\\\\\` meta-package|\\\\\`arqel-dev/framework\\\\\` meta-package|g" \
        -e "s|O \\\\\`arqel-dev/arqel\\\\\` meta-package|O \\\\\`arqel-dev/framework\\\\\` meta-package|g" \
        -e "s|The \\\\\`arqel-dev/arqel\\\\\` meta-package|The \\\\\`arqel-dev/framework\\\\\` meta-package|g" \
        -e "s|Meta-package \\\\\`arqel-dev/arqel\\\\\`|Meta-package \\\\\`arqel-dev/framework\\\\\`|g" \
        -e "s|| \\\\\`arqel-dev/arqel\\\\\` | Meta-package|| \\\\\`arqel-dev/framework\\\\\` | Meta-package|g" \
        "{}") || true
' 2>&1 | head -200
```

Expected: a unified diff showing planned changes. Verify visually that no `github.com/arqel-dev/arqel` URLs are touched (the patterns require `composer`, `require`, `meta-package`, or `:` — none of those match URL contexts).

If any URL is touched in the preview, **STOP** and refine the sed patterns. Do not proceed.

- [ ] **Step 2: Apply sed in-place for real**

```bash
cd /home/diogo/PhpstormProjects/arqel
find . \( -name "*.md" -o -name "*.yml" -o -name "*.yaml" \) \
  -not -path "./node_modules/*" -not -path "./vendor/*" -not -path "./apps/demo/node_modules/*" -not -path "./apps/demo/vendor/*" \
  -not -path "./docs/superpowers/specs/*" \
  -print0 \
| xargs -0 sed -i \
    -e 's|composer require arqel-dev/arqel\b|composer require arqel-dev/framework|g' \
    -e 's|composer -d \([^ ][^ ]*\) require arqel-dev/arqel\b|composer -d \1 require arqel-dev/framework|g' \
    -e "s|'arqel-dev/arqel:|'arqel-dev/framework:|g" \
    -e 's|"arqel-dev/arqel":|"arqel-dev/framework":|g' \
    -e 's|`arqel-dev/arqel` é o \*\*meta-package\*\*|`arqel-dev/framework` é o **meta-package**|g' \
    -e 's|`arqel-dev/arqel` is the \*\*meta-package\*\*|`arqel-dev/framework` is the **meta-package**|g' \
    -e 's|`arqel-dev/arqel` meta-package|`arqel-dev/framework` meta-package|g' \
    -e 's|O `arqel-dev/arqel` meta-package|O `arqel-dev/framework` meta-package|g' \
    -e 's|The `arqel-dev/arqel` meta-package|The `arqel-dev/framework` meta-package|g' \
    -e 's|Meta-package `arqel-dev/arqel`|Meta-package `arqel-dev/framework`|g' \
    -e 's|| `arqel-dev/arqel` | Meta-package|| `arqel-dev/framework` | Meta-package|g'
```

Note: `--include`/`--exclude` flags vary by find/sed version. The `-not -path` filters cover the directories we exclude.

Note: the spec excludes `docs/superpowers/specs/` so the spec file itself (which talks about both names by design) is not mutated.

- [ ] **Step 3: Final validation grep — must be empty (excluding KEEP contexts)**

```bash
cd /home/diogo/PhpstormProjects/arqel
grep -rn "arqel-dev/arqel" --include="*.php" --include="*.json" --include="*.md" --include="*.ts" --include="*.tsx" --include="*.yml" --include="*.yaml" \
  -- . ':!node_modules' ':!vendor' ':!apps/demo/vendor' ':!apps/demo/node_modules' ':!docs/superpowers/specs' \
  | grep -vE 'github\.com/arqel-dev/arqel|arqel-dev/arqel\.git|arqel-dev/arqel/(discussions|security|issues|actions|compare|pull|releases|wiki|tags)' \
  | grep -v '^\.gitignore'
```

Expected: empty output (or only lines that are clearly intentional GitHub URLs missed by the regex — read each manually). If non-empty, fix each line manually with Edit.

- [ ] **Step 4: Diff sanity check — count files changed and confirm no unintended changes**

```bash
git -C /home/diogo/PhpstormProjects/arqel diff --stat -- '*.md' '*.yml' '*.yaml' '*.json' '*.php' \
  | tail -5
```

Expected: ~120-140 files changed across docs/PLANNING/composer.json/SKILL.md. Spot-check 2-3 random files with `git diff path/file.md` to confirm only `arqel-dev/arqel` → `arqel-dev/framework` changes.

- [ ] **Step 5: Run linting on changed PHP files**

```bash
cd /home/diogo/PhpstormProjects/arqel/packages/cli && vendor/bin/pint --test src/Generators/SetupScriptGenerator.php src/Commands/NewCommand.php
cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pint --test src/Console/DoctorCommand.php 2>/dev/null || true
```

Expected: pass (no formatting issues introduced).

- [ ] **Step 6: Stage explicit paths only (not the pre-existing dirty files)**

```bash
cd /home/diogo/PhpstormProjects/arqel
# Capture the list of changed files from the rename, then explicitly add them
git diff --name-only -- ':!CLAUDE.md' ':!PLANNING/07-roadmap-fases.md' ':!pnpm-lock.yaml' ':!scripts/trigger-packagist-update.sh' \
  | xargs git add --
git add .gitignore  # for the audit-file ignore added in Task 1 step 2
git status --short | grep -v '^??\|^.M CLAUDE.md\|^.M PLANNING/07\|^.M pnpm-lock\|^.M scripts/trigger-packagist'
```

Verify with `git diff --cached --stat` that ONLY rename-related files are staged. If you see `CLAUDE.md`, `pnpm-lock.yaml`, `PLANNING/07-roadmap-fases.md`, or `scripts/trigger-packagist-update.sh` in `--cached`, run `git restore --staged <file>` to un-stage.

- [ ] **Step 7: Commit Phase 1**

```bash
cd /home/diogo/PhpstormProjects/arqel
git -c user.email="diogo.coutinho.ads@gmail.com" -c user.name="Diogo C. Coutinho" commit --signoff -m "$(cat <<'EOF'
chore(deps): finalize meta-package rename arqel-dev/arqel → arqel-dev/framework

Completes the partial rename. arqel-dev/arqel is now reserved exclusively
for GitHub repository URLs (clones, issues, security, compare). arqel-dev/framework
is the canonical Composer package name for the meta-package that downstream apps
install.

Affected: packages/arqel/composer.json (name field), apps/demo/composer.json
(require), CLI generator/test PHP files, ~120 docs (.md/.yml) across EN/PT-BR/ES,
SKILL.md files, CI workflows.

GitHub URLs intentionally preserved as arqel-dev/arqel.

Refs: docs/superpowers/specs/2026-05-06-fresh-laravel-e2e-validation-design.md (Phase 1)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

If commitlint rejects, the most likely reason is scope. Allowed scopes for this change: `deps` (dependency rename) or `docs` (since most edits are docs). Use `deps` first.

If pre-commit fails (biome, etc.), fix the underlying issue (do NOT use `--no-verify`).

- [ ] **Step 8: Verify final state**

```bash
git log -1 --stat HEAD | head -10
git status --short
```

Expected: commit summary shows ~140 files changed; status shows only the pre-existing dirty files still untracked/modified.

---

## Phase 1 acceptance gate

Before proceeding to Phase 2, confirm ALL:

- [ ] `git log -1 HEAD` is the rename commit, signed off, with valid scope
- [ ] `grep -rn "arqel-dev/arqel" --include="*.php" --include="*.json" --include="*.md" -- . ':!node_modules' ':!vendor' ':!docs/superpowers/specs' | grep -vE 'github\.com/arqel-dev/arqel|arqel-dev/arqel\.git|arqel-dev/arqel/(discussions|security|issues|actions|compare|pull|releases|wiki|tags)' | grep -v '^\.gitignore'` produces empty output
- [ ] `composer -d packages/arqel validate` says valid
- [ ] `composer -d apps/demo validate` says valid (note: composer.json now requires `arqel-dev/framework` but path repo still points at `packages/arqel/` which now declares itself as `arqel-dev/framework` — matching!)

If any gate fails, fix before Phase 2. **Push** is deferred until end of Phase 2.

---

# PHASE 2 — Recreate apps/demo from scratch

## Task 5: Backup current apps/demo

**Files:**
- Move: `apps/demo` → `apps/demo-old`

- [ ] **Step 1: Confirm working tree state**

```bash
git -C /home/diogo/PhpstormProjects/arqel status --short
```

Expected: only the pre-existing dirty files (CLAUDE.md, PLANNING/07, pnpm-lock.yaml, scripts/trigger-packagist-update.sh, PLANNING/13, packages-js/mcp-server/) are listed. No staged changes (Phase 1 commit already made).

- [ ] **Step 2: Move apps/demo to apps/demo-old**

```bash
cd /home/diogo/PhpstormProjects/arqel
mv apps/demo apps/demo-old
ls -la apps/
```

Expected: `apps/demo-old/` exists; `apps/demo/` does not.

- [ ] **Step 3: Add apps/demo-old to .gitignore**

Edit `.gitignore`, append:

```
# Phase-2 transient backup of pre-recreate apps/demo
/apps/demo-old/
```

This prevents the backup from being staged accidentally during the next commits.

- [ ] **Step 4: Verify backup is invisible to git**

```bash
git -C /home/diogo/PhpstormProjects/arqel status --short | grep -i 'demo-old'
```

Expected: empty output (the backup is ignored).

- [ ] **Step 5: No commit yet** — `.gitignore` change goes in with Task 6.

---

## Task 6: Create fresh Laravel 12 skeleton + path repos + meta-package

**Files:**
- Create: `apps/demo/` (new Laravel 12 skeleton, generated by `laravel new`)
- Modify: `apps/demo/composer.json` (after generation, to add path repos)

- [ ] **Step 1: Verify laravel installer is available**

```bash
which laravel || composer global show laravel/installer 2>/dev/null | head -3
```

If missing, run: `composer global require laravel/installer`. Expected: `laravel` binary on PATH or `laravel/installer` listed.

- [ ] **Step 2: Generate fresh Laravel 12 skeleton**

```bash
cd /home/diogo/PhpstormProjects/arqel/apps
laravel new demo --no-interaction --git=false
```

The `--git=false` is critical — we are inside an existing git repo and don't want a nested `.git/`. Expected: `apps/demo/` contains a fresh Laravel 12 skeleton (`artisan`, `composer.json`, `app/`, etc.).

If `--no-interaction` prompts for "starter kit", default is none (no Breeze/Jetstream) — that is what we want; Arqel auth installer will scaffold its own pages.

- [ ] **Step 3: Verify skeleton generated correctly**

```bash
cd /home/diogo/PhpstormProjects/arqel/apps/demo
php artisan --version
cat composer.json | head -20
```

Expected: `Laravel Framework 12.x.y`. composer.json has `laravel/framework: ^12.0`.

- [ ] **Step 4: Add Arqel meta-package + path repos to composer.json**

Read `apps/demo/composer.json`, then use Edit tool to:

1. Add `"arqel-dev/framework": "@dev"` to the `require` block
2. Add a `repositories` block with 8 path repos

Use this Edit (replace the closing of `require` block + add repositories):

old_string (the require block closing — adapt to actual file):
```json
        "laravel/tinker": "^2.10.1"
    },
```

new_string:
```json
        "laravel/tinker": "^2.10.1",
        "arqel-dev/framework": "@dev"
    },
    "repositories": [
        { "type": "path", "url": "../../packages/arqel",          "options": { "symlink": true } },
        { "type": "path", "url": "../../packages/core",           "options": { "symlink": true } },
        { "type": "path", "url": "../../packages/auth",           "options": { "symlink": true } },
        { "type": "path", "url": "../../packages/fields",         "options": { "symlink": true } },
        { "type": "path", "url": "../../packages/form",           "options": { "symlink": true } },
        { "type": "path", "url": "../../packages/actions",        "options": { "symlink": true } },
        { "type": "path", "url": "../../packages/nav",            "options": { "symlink": true } },
        { "type": "path", "url": "../../packages/table",          "options": { "symlink": true } }
    ],
```

If the actual file has a different structure (e.g., `require` ends with a different package), adapt the Edit `old_string` to match exactly.

Also add to `config.allow-plugins` if Composer prompts about Arqel-related plugins (unlikely in v0.8 — Arqel uses Service Provider auto-discovery, not plugins).

- [ ] **Step 5: composer validate**

```bash
composer -d /home/diogo/PhpstormProjects/arqel/apps/demo validate
```

Expected: `./composer.json is valid`.

- [ ] **Step 6: composer install**

```bash
composer -d /home/diogo/PhpstormProjects/arqel/apps/demo install 2>&1 | tail -30
```

Expected: all 8 Arqel path packages resolve. `inertiajs/inertia-laravel` is pulled transitively (peer of `arqel-dev/framework`). Service providers auto-discovered: should see `Arqel\Core\ArqelServiceProvider`, `Arqel\Auth\AuthServiceProvider`, etc. listed.

If install fails:
- "Could not find package arqel-dev/framework" → check that `packages/arqel/composer.json` `name` field is `arqel-dev/framework` (Phase 1 should have done this)
- "Circular dependency" → STOP and surface
- "Conflict: arqel-dev/core ... requires php ^8.3 but you have 8.x" → check actual PHP version, may need to adjust min

- [ ] **Step 7: Smoke test — list registered providers**

```bash
php /home/diogo/PhpstormProjects/arqel/apps/demo/artisan about 2>&1 | grep -i 'arqel\|inertia' | head -10
```

Expected: at least one Arqel provider line listed.

- [ ] **Step 8: No commit yet** — wait for full skeleton + install to be verified at end of Task 7.

---

## Task 7: Run arqel:install + migrate + make-user

**Files:** (these are produced by `arqel:install`, not authored by us)
- Created by installer: `apps/demo/config/arqel.php`
- Created by installer: `apps/demo/app/Providers/ArqelServiceProvider.php`
- Modified by installer: `apps/demo/bootstrap/providers.php`
- Created by installer: `apps/demo/app/Http/Middleware/HandleInertiaRequests.php`
- Created by installer: `apps/demo/vite.config.ts` (replaces default `vite.config.js`)
- Created by installer: `apps/demo/app/Arqel/Resources/UserResource.php`
- Created by installer: `apps/demo/resources/views/arqel/layout.blade.php`
- Created by installer: `apps/demo/resources/js/app.tsx`
- Created by installer: `apps/demo/resources/css/app.css`
- Created by installer: `apps/demo/public/login-hero.svg`
- Created by installer: `apps/demo/AGENTS.md`

- [ ] **Step 1: Run arqel:install**

```bash
php /home/diogo/PhpstormProjects/arqel/apps/demo/artisan arqel:install 2>&1 | tail -50
```

Expected output mentions all 11 install steps (config publish, ArqelServiceProvider scaffold, providers.php registration, HandleInertiaRequests middleware, vite.config.ts, UserResource scaffold, layout.blade.php, app.tsx, login-hero.svg, AGENTS.md, frontend deps install).

If installer fails at a specific step:
- Capture full error
- Read the relevant file in `packages/core/src/Console/InstallCommand.php`
- Fix in `packages/core/`, save, the path-repo symlink propagates, re-run `arqel:install --force`
- Each fix gets its own commit `fix(core): ...` referencing the failure

- [ ] **Step 2: Verify scaffolded files exist**

```bash
ls -la /home/diogo/PhpstormProjects/arqel/apps/demo/{config/arqel.php,app/Providers/ArqelServiceProvider.php,app/Http/Middleware/HandleInertiaRequests.php,vite.config.ts,app/Arqel/Resources/UserResource.php,resources/views/arqel/layout.blade.php,resources/js/app.tsx,public/login-hero.svg,AGENTS.md}
```

Expected: all 9 files exist.

- [ ] **Step 3: Verify ArqelServiceProvider registered in bootstrap/providers.php**

```bash
grep -n 'ArqelServiceProvider' /home/diogo/PhpstormProjects/arqel/apps/demo/bootstrap/providers.php
```

Expected: `App\Providers\ArqelServiceProvider::class` listed.

- [ ] **Step 4: Run migrations**

```bash
php /home/diogo/PhpstormProjects/arqel/apps/demo/artisan migrate --force 2>&1 | tail -10
```

Expected: default Laravel migrations run (users, password_reset_tokens, sessions, cache, jobs). No Arqel-specific migrations in v0.8 (those come in audit/widgets, Phase 2).

- [ ] **Step 5: Create admin user**

```bash
php /home/diogo/PhpstormProjects/arqel/apps/demo/artisan arqel:make-user --name=Admin --email=admin@arqel.test --password=password 2>&1 | tail -5
```

Expected: `User created successfully` or similar.

- [ ] **Step 6: Run arqel:doctor — must be 0 fails**

```bash
php /home/diogo/PhpstormProjects/arqel/apps/demo/artisan arqel:doctor --strict 2>&1 | tail -20
```

Expected: exit 0, `0 fail` in summary. Warns are acceptable but should be reviewed (typically `cache.driver=array`, `session.driver=file` in dev).

If `--strict` returns exit 1 due to warns, downgrade to non-strict for now (warns are informational).

- [ ] **Step 7: Install JS deps (should be auto-installed by arqel:install but double-check)**

```bash
ls /home/diogo/PhpstormProjects/arqel/apps/demo/node_modules/@arqel-dev/ 2>/dev/null
```

Expected: directories `auth`, `fields`, `hooks`, `react`, `types`, `ui`. If missing, the auto-install step failed silently — re-run from monorepo root: `pnpm install`.

- [ ] **Step 8: Build frontend once**

```bash
cd /home/diogo/PhpstormProjects/arqel/apps/demo && pnpm build 2>&1 | tail -20
```

Expected: Vite builds successfully, `public/build/` populated, no errors. If build fails on TypeScript types or shadcn/Tailwind, fix in `packages-js/<pkg>/src/...` and rebuild via `pnpm -C packages-js/<pkg> build`.

- [ ] **Step 9: No commit yet** — full skeleton + install verified together at end of Task 8.

---

## Task 8: Commit demo skeleton (without PostResource yet)

- [ ] **Step 1: Stage explicit demo paths only**

```bash
cd /home/diogo/PhpstormProjects/arqel
git add apps/demo/composer.json apps/demo/composer.lock apps/demo/package.json apps/demo/pnpm-lock.yaml 2>/dev/null
git add apps/demo/.env.example apps/demo/.gitignore 2>/dev/null
git add apps/demo/{app,bootstrap,config,database,public,resources,routes,tests,vite.config.ts,artisan,phpunit.xml,README.md,AGENTS.md} 2>/dev/null
git add .gitignore  # for apps/demo-old/ ignore added in Task 5 step 3
git status --short | head -30
```

- [ ] **Step 2: Verify nothing unrelated is staged**

```bash
git diff --cached --name-only | grep -v '^apps/demo\|^.gitignore'
```

Expected: empty. If anything else, unstage it.

- [ ] **Step 3: Commit**

```bash
cd /home/diogo/PhpstormProjects/arqel
git -c user.email="diogo.coutinho.ads@gmail.com" -c user.name="Diogo C. Coutinho" commit --signoff -m "$(cat <<'EOF'
feat(demo): recreate apps/demo from fresh Laravel 12 + arqel:install

Phase 2 step 1 of e2e validation: backup the previous apps/demo to
apps/demo-old/ (gitignored), generate a fresh Laravel 12 skeleton via
laravel new, wire arqel-dev/framework via path repositories, run
arqel:install end-to-end (11 steps idempotent: config publish, provider
scaffold, providers.php registration, Inertia middleware, vite.config.ts,
UserResource, blade layout, app.tsx, login-hero.svg, AGENTS.md, frontend
deps install).

Verified:
- composer install resolves all 8 path packages + inertia-laravel transitively
- arqel:install completes without errors
- arqel:make-user creates admin@arqel.test
- arqel:doctor reports 0 fails
- pnpm build succeeds (Vite 7 + Tailwind v4 + shadcn)

PostResource showcase comes in the next commit.

Refs: docs/superpowers/specs/2026-05-06-fresh-laravel-e2e-validation-design.md (Phase 2 Tasks 5-8)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

Allowed scope: `demo`. Pre-commit hooks may run lint-staged on `apps/demo/**` JS/TS files; if biome flags shadcn-generated code that wasn't excluded, add `"!apps/demo/src/shadcn"` to `biome.json` first (separate fix commit).

---

## Task 9: Add Post model + migration + factory + seeder

**Files:**
- Create: `apps/demo/database/migrations/2026_05_06_000001_create_posts_table.php`
- Create: `apps/demo/app/Models/Post.php`
- Create: `apps/demo/database/factories/PostFactory.php`
- Create: `apps/demo/database/seeders/PostSeeder.php`
- Modify: `apps/demo/database/seeders/DatabaseSeeder.php` to call PostSeeder

- [ ] **Step 1: Create migration**

Write file `/home/diogo/PhpstormProjects/arqel/apps/demo/database/migrations/2026_05_06_000001_create_posts_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->text('body')->nullable();
            $t->string('status')->default('draft');
            $t->boolean('featured')->default(false);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

- [ ] **Step 2: Create Post model**

Write file `/home/diogo/PhpstormProjects/arqel/apps/demo/app/Models/Post.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'status',
        'featured',
        'published_at',
        'user_id',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Create PostFactory**

Write file `/home/diogo/PhpstormProjects/arqel/apps/demo/database/factories/PostFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
final class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(rand(3, 8));

        return [
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(4),
            'body' => fake()->paragraphs(rand(2, 5), true),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'featured' => fake()->boolean(20),
            'published_at' => fake()->optional(0.7)->dateTimeBetween('-1 year'),
            'user_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
        ];
    }
}
```

- [ ] **Step 4: Create PostSeeder**

Write file `/home/diogo/PhpstormProjects/arqel/apps/demo/database/seeders/PostSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Post;
use Illuminate\Database\Seeder;

final class PostSeeder extends Seeder
{
    public function run(): void
    {
        Post::factory()->count(25)->create();
    }
}
```

- [ ] **Step 5: Wire PostSeeder into DatabaseSeeder**

Read `apps/demo/database/seeders/DatabaseSeeder.php` and use Edit to add `$this->call(PostSeeder::class);` inside the `run()` method (after any existing user creation logic).

- [ ] **Step 6: Run migration + seed**

```bash
cd /home/diogo/PhpstormProjects/arqel/apps/demo
php artisan migrate --force
php artisan db:seed --class=PostSeeder --force
php artisan tinker --execute='echo \App\Models\Post::count();'
```

Expected: `25`.

- [ ] **Step 7: No commit yet** — combined with Task 10 commit.

---

## Task 10: Add PostResource + write feature test

**Files:**
- Create: `apps/demo/app/Arqel/Resources/PostResource.php`
- Create: `apps/demo/tests/Feature/PostResourceTest.php`

- [ ] **Step 1: Write the failing feature test FIRST (TDD)**

Write file `/home/diogo/PhpstormProjects/arqel/apps/demo/tests/Feature/PostResourceTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'email' => 'admin-test@arqel.test',
    ]);
});

it('lists posts on the resource index', function () {
    Post::factory()->count(3)->create(['user_id' => $this->admin->id]);

    actingAs($this->admin)
        ->get('/admin/posts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('arqel/Resources/Index')
            ->has('records.data', 3)
        );
});

it('renders the create form for posts', function () {
    actingAs($this->admin)
        ->get('/admin/posts/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('arqel/Resources/Create')
            ->has('fields')
            ->has('form')
        );
});

it('persists a new post via POST /admin/posts', function () {
    actingAs($this->admin)
        ->post('/admin/posts', [
            'title' => 'Test post',
            'slug' => 'test-post',
            'status' => 'draft',
            'featured' => false,
            'user_id' => $this->admin->id,
        ])
        ->assertRedirect();

    expect(Post::where('slug', 'test-post')->exists())->toBeTrue();
});

it('rejects creation without required title', function () {
    actingAs($this->admin)
        ->post('/admin/posts', ['slug' => 'no-title', 'status' => 'draft'])
        ->assertSessionHasErrors('title');
});
```

NOTE: the exact Inertia component name (`'arqel/Resources/Index'`) and the `records.data` shape depend on how `InertiaDataBuilder::buildIndexData` serializes — verify against `packages/core/src/Support/InertiaDataBuilder.php` and adjust the assertion if the prop key differs.

- [ ] **Step 2: Run test — expect FAIL (PostResource does not exist yet)**

```bash
cd /home/diogo/PhpstormProjects/arqel/apps/demo
php artisan test --filter=PostResourceTest 2>&1 | tail -20
```

Expected: FAIL — "Class App\Arqel\Resources\PostResource not found" or "404 on /admin/posts".

- [ ] **Step 3: Write the PostResource**

Write file `/home/diogo/PhpstormProjects/arqel/apps/demo/app/Arqel/Resources/PostResource.php`:

```php
<?php

declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Post;
use Arqel\Actions\Actions;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\Types\BooleanField;
use Arqel\Fields\Types\DateTimeField;
use Arqel\Fields\Types\SelectField;
use Arqel\Fields\Types\TextareaField;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\BooleanColumn;
use Arqel\Table\Columns\DateColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Filters\TernaryFilter;
use Arqel\Table\Table;

final class PostResource extends Resource
{
    public static string $model = Post::class;

    public static ?string $navigationIcon = 'file-text';

    public static ?string $navigationGroup = 'Content';

    public static ?int $navigationSort = 10;

    public function form(): Form
    {
        return Form::make()
            ->columns(2)
            ->schema([
                Section::make('Content')->schema([
                    TextField::make('title')->required(),
                    TextField::make('slug')->required(),
                    TextareaField::make('body')->rows(8),
                ]),
                Section::make('Meta')->schema([
                    SelectField::make('status')->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ])->required(),
                    BooleanField::make('featured'),
                    DateTimeField::make('published_at'),
                ]),
            ]);
    }

    public function table(): Table
    {
        return Table::make()
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                BadgeColumn::make('status')->colors([
                    'success' => 'published',
                    'warning' => 'draft',
                    'destructive' => 'archived',
                ]),
                BooleanColumn::make('featured'),
                DateColumn::make('published_at')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ]),
                TernaryFilter::make('featured'),
            ])
            ->actions([
                Actions::edit(),
                Actions::delete(),
            ])
            ->bulkActions([
                Actions::deleteBulk(),
            ])
            ->defaultSort('published_at', 'desc');
    }
}
```

NOTE: API surface (`Form::make()`, `Section::make()`, `TextField::make()`, `Table::make()`, `Actions::edit()`) is per `packages/{form,fields,table,actions}/SKILL.md`. If a method does not exist (e.g., `Section::make()->schema()` differs), check the SKILL.md for the canonical builder API and adapt. Do NOT invent API.

- [ ] **Step 4: Register PostResource in the panel**

Read `apps/demo/app/Providers/ArqelServiceProvider.php` and verify it has a `Panel::configure()` block. The default install scaffolds a panel with `UserResource` only — extend it. Use Edit:

old_string (will look like):
```php
            ->resources([
                \App\Arqel\Resources\UserResource::class,
            ]);
```

new_string:
```php
            ->resources([
                \App\Arqel\Resources\UserResource::class,
                \App\Arqel\Resources\PostResource::class,
            ]);
```

If the panel scaffold uses a different pattern (e.g., autoload from `app/Arqel/Resources/`), no edit needed — the `PostResource` will be picked up automatically.

- [ ] **Step 5: Run tests again — expect at least the listing test to pass**

```bash
cd /home/diogo/PhpstormProjects/arqel/apps/demo
php artisan test --filter=PostResourceTest 2>&1 | tail -30
```

Expected: 4/4 pass. If individual tests fail, debug per-test:
- "404 on /admin/posts" → resource not registered, route not generated; check Panel config + ResourceController routes
- "Component name mismatch" → adjust assertion to match actual Inertia component path
- "Validation errors" → check Field rules; `title` required maps to Laravel `required` rule via FieldRulesExtractor

- [ ] **Step 6: Stage paths + commit**

```bash
cd /home/diogo/PhpstormProjects/arqel
git add apps/demo/database/migrations/2026_05_06_000001_create_posts_table.php
git add apps/demo/app/Models/Post.php
git add apps/demo/database/factories/PostFactory.php
git add apps/demo/database/seeders/PostSeeder.php
git add apps/demo/database/seeders/DatabaseSeeder.php
git add apps/demo/app/Arqel/Resources/PostResource.php
git add apps/demo/app/Providers/ArqelServiceProvider.php
git add apps/demo/tests/Feature/PostResourceTest.php
git diff --cached --name-only
```

Verify only the 8 files above are staged.

```bash
git -c user.email="diogo.coutinho.ads@gmail.com" -c user.name="Diogo C. Coutinho" commit --signoff -m "$(cat <<'EOF'
feat(demo): add PostResource showcase exercising fields, form, table, actions

Adds a Post model + migration + factory + 25-record seeder, and a PostResource
that exercises:

  - Fields: TextField, TextareaField, SelectField, BooleanField, DateTimeField (5/21 types)
  - Form: 2-column layout with 2 Sections (Content + Meta)
  - Table: TextColumn (searchable+sortable), BadgeColumn (color map), BooleanColumn,
    DateColumn (sortable), SelectFilter, TernaryFilter, defaultSort desc
  - Actions: row edit/delete, bulk deleteBulk

Feature test (Pest) covers: index lists records, create form renders, store
persists, validation rejects missing title.

Refs: docs/superpowers/specs/2026-05-06-fresh-laravel-e2e-validation-design.md (Phase 2 Tasks 9-10)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: Manual browser-based acceptance walkthrough

**Files:** none (this is a manual verification step)

- [ ] **Step 1: Boot dev servers**

Open two terminals (or use tmux/concurrent):

```bash
# Terminal 1
cd /home/diogo/PhpstormProjects/arqel/apps/demo && php artisan serve

# Terminal 2
cd /home/diogo/PhpstormProjects/arqel/apps/demo && pnpm dev
```

Or single command via composer script:
```bash
cd /home/diogo/PhpstormProjects/arqel/apps/demo && composer dev
```

Expected: `php artisan serve` listens on `http://127.0.0.1:8000`. Vite dev server on `http://127.0.0.1:5173`.

- [ ] **Step 2: Run the 15 acceptance criteria from the spec**

Open browser at `http://127.0.0.1:8000/admin/login`. Walk through each criterion. Track in a checklist:

| # | Criterion | Pass? |
|---|---|---|
| 1 | Boot serve+vite no errors |  |
| 2 | /admin/login renders login-04 layout |  |
| 3 | Login admin@arqel.test/password redirects to /admin |  |
| 4 | Sidebar shows System>Users + Content>Posts |  |
| 5 | /admin/posts shows DataTable with 25 posts |  |
| 6 | Sort by title asc / published_at desc works |  |
| 7 | Search 'draft' filters; SelectFilter status=published works |  |
| 8 | TernaryFilter featured=true works |  |
| 9 | Pagination next loads via Inertia partial reload |  |
| 10 | /admin/posts/create renders 2-col form, 2 sections |  |
| 11 | Submit empty title shows ":attribute is required" |  |
| 12 | /admin/posts/{id}/edit pre-populates; submit persists |  |
| 13 | RowAction Delete shows confirmation modal; confirms removes record |  |
| 14 | Select 3 rows, bulk Delete; confirms removes all 3 |  |
| 15 | Cmd+K opens Command Palette; nav:posts works; theme toggle works |  |

For each FAIL, capture the error (browser console, server log, network tab) and fix in `packages/<pkg>/src/...`. Each fix is its own commit.

- [ ] **Step 3: Run pest test suite for apps/demo**

```bash
cd /home/diogo/PhpstormProjects/arqel/apps/demo && php artisan test 2>&1 | tail -20
```

Expected: all green (skeleton 2 tests + PostResourceTest 4 tests = 6 pass).

- [ ] **Step 4: No commit unless fixes were needed**

If criteria 1-15 all pass on first try, no additional commit needed in Task 11. If fixes were needed, each gets its own `fix(<scope>):` commit during the walkthrough.

---

## Task 12: Cleanup — diff old demo, port useful items, remove backup

- [ ] **Step 1: Compare old vs new demo**

```bash
diff -rq /home/diogo/PhpstormProjects/arqel/apps/demo-old /home/diogo/PhpstormProjects/arqel/apps/demo 2>&1 \
  | grep -vE 'node_modules|vendor|public/build|storage/(framework|logs)|\.env$|composer\.lock|pnpm-lock' \
  | head -40
```

Review unique files in `apps/demo-old/` — anything custom worth preserving?
- Custom seeders? → port
- Custom routes? → port
- README modifications? → port

For each item to port: `cp apps/demo-old/path apps/demo/path` and add to a follow-up commit.

- [ ] **Step 2: Final smoke test**

```bash
cd /home/diogo/PhpstormProjects/arqel/apps/demo && php artisan test && pnpm test 2>&1 | tail -10
```

Expected: green.

- [ ] **Step 3: Remove backup**

```bash
rm -rf /home/diogo/PhpstormProjects/arqel/apps/demo-old
```

- [ ] **Step 4: Remove the apps/demo-old/ ignore line from .gitignore**

Edit `.gitignore` and remove the lines added in Task 5 step 3:
```
# Phase-2 transient backup of pre-recreate apps/demo
/apps/demo-old/
```

- [ ] **Step 5: Commit cleanup**

If anything was ported in Step 1, stage explicitly + commit:

```bash
cd /home/diogo/PhpstormProjects/arqel
git add .gitignore
# git add <ported paths>  if any
git -c user.email="diogo.coutinho.ads@gmail.com" -c user.name="Diogo C. Coutinho" commit --signoff -m "$(cat <<'EOF'
chore(demo): remove apps/demo-old backup, finalize Phase 2

Phase 2 acceptance criteria all passed; backup is no longer needed.
.gitignore restored to baseline.

Refs: docs/superpowers/specs/2026-05-06-fresh-laravel-e2e-validation-design.md (Phase 2 Task 12)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

If nothing ported, the commit only modifies `.gitignore`.

---

## Phase 2 acceptance gate

Before proceeding to Phase 3, confirm ALL:

- [ ] All 15 manual acceptance criteria from Task 11 step 2 pass
- [ ] `php artisan test` (in apps/demo) green
- [ ] `pnpm build` green
- [ ] `arqel:doctor` 0 fails
- [ ] `apps/demo-old/` removed
- [ ] Phase 2 commit history is reviewable: skeleton commit, PostResource commit, optional fix commits, cleanup commit

**Push** is deferred until Phase 3 outcome is known (if Phase 3 reveals need for tag, we want monorepo state pushed first).

---

# PHASE 3 — External smoke test via Packagist

## Task 13: Push monorepo to remote

- [ ] **Step 1: Verify everything is committed**

```bash
git -C /home/diogo/PhpstormProjects/arqel status --short
```

Expected: only the pre-existing dirty files (CLAUDE.md, PLANNING/07, pnpm-lock.yaml, scripts/trigger-packagist-update.sh, PLANNING/13, packages-js/mcp-server/) are listed. Phase 1 and Phase 2 work fully committed.

- [ ] **Step 2: Push main**

```bash
cd /home/diogo/PhpstormProjects/arqel
git push origin main
```

Expected: push succeeds. CI may run; do not block on it for Phase 3 — we will only need a release tag if Phase 3 fails Caso A.

---

## Task 14: Create external test project

**Files:**
- Create: `~/PhpstormProjects/arqel-test/` (new git repo, fresh Laravel 12)

- [ ] **Step 1: Generate skeleton + git init**

```bash
cd ~/PhpstormProjects
laravel new arqel-test --no-interaction --git=false
cd arqel-test
git init
git add -A
git -c user.email="diogo.coutinho.ads@gmail.com" -c user.name="Diogo C. Coutinho" commit --signoff -m "chore: initial Laravel 12 skeleton from laravel new"
```

Expected: `~/PhpstormProjects/arqel-test/` has its own `.git/`, fresh Laravel 12.

- [ ] **Step 2: composer require arqel-dev/framework from Packagist**

```bash
cd ~/PhpstormProjects/arqel-test
composer require "arqel-dev/framework:^0.8" 2>&1 | tail -30
```

**Branch decision:**

- **Success** → continue to Step 3
- **"Could not find a version of arqel-dev/framework matching ^0.8 with sub-package arqel-dev/core only on dev-main"** → SKIP to Task 15 (Caso A — tag v0.8.2)
- **Other error** → STOP, capture, surface to user

- [ ] **Step 3: Run installer**

```bash
php artisan arqel:install 2>&1 | tail -30
php artisan migrate --force
php artisan arqel:make-user --name=Test --email=test@arqel.test --password=password
```

Expected: install runs cleanly, migrations apply, user created.

If install fails: this is **Caso B** — bug specific to Packagist install vs path repo install. Capture the failing step, compare `vendor/arqel-dev/framework/composer.json` vs `packages/arqel/composer.json` in the monorepo, fix in monorepo, jump to Task 15 (re-tag).

- [ ] **Step 4: Build frontend**

```bash
cd ~/PhpstormProjects/arqel-test
pnpm install
pnpm build 2>&1 | tail -10
```

Expected: build succeeds. If it fails on assets (Caso C), check `vite.config.ts` published by the installer matches what `apps/demo/vite.config.ts` looks like in the monorepo.

- [ ] **Step 5: Boot + manual smoke test**

```bash
cd ~/PhpstormProjects/arqel-test
composer dev
```

Open `http://127.0.0.1:8000/admin/login`. Walk through Phase 3 acceptance criteria:

| # | Criterion | Pass? |
|---|---|---|
| 1 | composer require resolved without errors |  |
| 2 | arqel:install ran without manual intervention |  |
| 3 | Login flow works (CSS/JS no 404s) |  |
| 4 | UserResource renders index + edit |  |
| 5 | Theme toggle persists on refresh |  |

- [ ] **Step 6: Commit external test project state**

```bash
cd ~/PhpstormProjects/arqel-test
git add -A
git -c user.email="diogo.coutinho.ads@gmail.com" -c user.name="Diogo C. Coutinho" commit --signoff -m "chore: install arqel-dev/framework, run arqel:install, create admin user"
```

This is in the *external* repo, not the monorepo. The external repo lives only locally (no remote push) — purely for reproducibility.

---

## Task 15: [Conditional] Tag v0.8.2 if Phase 3 Caso A or B triggered

This task only runs if Phase 3 Step 2 or Step 3 failed and the diagnosis points to needing a fresh release. User authorized auto-tag in the brainstorming.

- [ ] **Step 1: Verify monorepo main is the source of truth**

```bash
cd /home/diogo/PhpstormProjects/arqel
git log --oneline -5
git status --short
```

Expected: latest commit is the Phase 2 cleanup or a fix from Phase 3 debugging (which should also be committed already).

- [ ] **Step 2: Check current latest tag**

```bash
git tag -l | sort -V | tail -5
```

Expected: `v0.8.1` is the highest existing tag.

- [ ] **Step 3: Tag v0.8.2 with annotated message**

```bash
cd /home/diogo/PhpstormProjects/arqel
git tag -a v0.8.2 -m "Release v0.8.2

Brings Packagist meta-package and sub-packages in sync with monorepo:
  - arqel-dev/arqel → arqel-dev/framework rename finalized
  - apps/demo recreated from fresh Laravel 12 + PostResource showcase
  - End-to-end install flow validated on Laravel 12 + PHP 8.3

Refs: docs/superpowers/specs/2026-05-06-fresh-laravel-e2e-validation-design.md
"
```

- [ ] **Step 4: Push tag**

```bash
git push origin v0.8.2
```

This triggers `splitsh/lite` via `.github/workflows/release.yml` to propagate the tag to all sub-package repos. Packagist webhook picks up new versions within ~2-5 minutes.

- [ ] **Step 5: Wait for Packagist refresh, verify**

```bash
sleep 180
for pkg in framework core auth fields form actions nav table; do
  curl -s "https://packagist.org/packages/arqel-dev/${pkg}.json" \
    | python3 -c "import sys,json; d=json.loads(sys.stdin.read()); v=list(d.get('package',{}).get('versions',{}).keys()); print('arqel-dev/$pkg:', 'v0.8.2' in v)"
done
```

Expected: all 8 packages report `True` (have v0.8.2). If not, manually trigger Packagist webhook from GitHub repo settings or `bash scripts/trigger-packagist-update.sh`.

- [ ] **Step 6: Re-run Phase 3 from Task 14 Step 2**

Now `composer require arqel-dev/framework:^0.8` should resolve to v0.8.2. Re-validate criteria 1-5.

If it STILL fails, this is a release pipeline bug — surface to user with diagnostic data.

---

## Phase 3 acceptance gate

Before declaring the project complete, confirm ALL:

- [ ] `~/PhpstormProjects/arqel-test/` exists with successful install
- [ ] All 5 Phase 3 acceptance criteria pass
- [ ] If v0.8.2 was tagged: Packagist confirms all 8 packages have v0.8.2
- [ ] Monorepo main pushed to GitHub origin
- [ ] No regressions in `apps/demo` (re-run `php artisan test` if v0.8.2 was tagged to confirm path-repo install still works)

---

## Final summary commit (monorepo)

- [ ] **Step 1: Update CHANGELOG.md** (after all phases complete)

Read `CHANGELOG.md`, find the `[Unreleased]` section. Move its content under a new heading `[v0.8.2] — 2026-05-06` and create a fresh empty `[Unreleased]` heading above it. Add v0.8.2 release notes summarizing:

- Naming saneamento (Phase 1)
- apps/demo recreate + PostResource (Phase 2)
- E2E validation via Packagist (Phase 3)

Add the version compare link at the bottom:
```
[v0.8.2]: https://github.com/arqel-dev/arqel/compare/v0.8.1...v0.8.2
```

- [ ] **Step 2: Commit CHANGELOG**

```bash
cd /home/diogo/PhpstormProjects/arqel
git add CHANGELOG.md
git -c user.email="diogo.coutinho.ads@gmail.com" -c user.name="Diogo C. Coutinho" commit --signoff -m "$(cat <<'EOF'
docs(release): record v0.8.2 — naming saneamento + demo refresh + e2e validation

Refs: docs/superpowers/specs/2026-05-06-fresh-laravel-e2e-validation-design.md

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
git push origin main
```

(Skip this final commit if v0.8.2 was NOT tagged — i.e., Phase 3 succeeded with v0.8.1.)

---

# Self-review notes

**Spec coverage:**

| Spec section | Plan task |
|---|---|
| Fase 1 — Pass 1 (estruturais) | Task 2 |
| Fase 1 — Pass 2 (código) | Task 3 |
| Fase 1 — Pass 3 (sed massa) | Task 4 |
| Fase 1 — Validação | Task 4 step 3 + Phase 1 acceptance gate |
| Fase 2 — Backup | Task 5 |
| Fase 2 — Recriar skeleton | Task 6 |
| Fase 2 — arqel:install + migrate + make-user | Task 7 |
| Fase 2 — PostResource showcase | Tasks 9 + 10 |
| Fase 2 — 15 critérios | Task 11 |
| Fase 2 — Cleanup demo-old | Task 12 |
| Fase 3 — Skeleton externo | Task 14 |
| Fase 3 — Caso A remediation (tag v0.8.2) | Task 15 |
| Fase 3 — 5 critérios | Task 14 step 5 |
| CHANGELOG update on v0.8.2 | Final summary commit |

All spec sections covered.

**Type/method consistency:**

- `Form::make()`, `Section::make()`, `TextField::make()`, `Table::make()`, `Actions::edit()`, `Actions::delete()`, `Actions::deleteBulk()` — used consistently across Tasks 9, 10. Sourced from `packages/{form,fields,table,actions}/SKILL.md`.
- `Resource::form()`, `Resource::table()` — consistent with `packages/core/SKILL.md` Form payload integration (FORM-006).
- Commit scopes — `deps`, `demo`, `core`, `docs`, `release` — all in the commitlint-allowed list.

**Placeholders:** none.

**Ambiguities resolved inline:**

- Inertia component name in Task 10 step 1 marked as "verify against InertiaDataBuilder" with fallback instruction.
- ArqelServiceProvider edit in Task 10 step 4 has fallback "no edit needed if autoload pattern".

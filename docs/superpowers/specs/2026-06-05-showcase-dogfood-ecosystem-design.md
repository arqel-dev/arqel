# Arqel dogfooding ecosystem — comprehensive showcase app + autonomous bug-fix loop

> **Goal:** Build `apps/showcase`, a Laravel app that exercises ALL 20 `arqel-dev/*` packages, then run a multi-agent ecosystem that detects bugs across it, files GitHub issues for genuine framework bugs, fixes them (TDD + reviews + docs), opens reviewed/tested PRs, and merges them when CI is clean — looping until two consecutive detection rounds find nothing new.

**Mode:** Fully autonomous including merge. Hard safeguards (CI CLEAN required, no force-push, no `--no-verify`, no `--admin`, per-package merge serialization, persistent seen-registry to converge).

**No real-money spend (CLAUDE.md rule #4).** Nothing in this ecosystem may incur a financial charge. AI features use stub/fake providers (no real LLM API calls). Anything requiring paid credentials (Stripe for marketplace, a hosted Echo/Reverb server, cloud deploys) is exercised only with local fakes/in-memory drivers, or documented as out-of-scope with a reason — never wired to a billable service. If a package genuinely cannot be exercised without spend, that is recorded as a coverage gap, not faked or charged.

---

## Phase 0 — Scaffold `apps/showcase` (one-time, barrier)

A new reference Laravel app under `apps/showcase` that uses every publishable package, mirroring the structure of `apps/demo`/`apps/tenant-demo` (path repos, `arqel.layout` Inertia root, `arqel:install` conventions, tracked `storage/framework/*` dirs to avoid the CI 500 gotcha).

**Coverage targets (the 20 packages):**
- **core / fields / fields-advanced / form / table / actions:** several Resources covering all field types (text, textarea, email, url, password, slug, number, currency, boolean, toggle, select, multiselect, radio, date, datetime, file, image, hidden, belongsTo, hasMany) AND advanced fields (RichText, Markdown, Code, Repeater, Builder, KeyValue, Tags, Wizard); layout-aware `form()` (Section/Tabs/Grid); tables with sortable/searchable columns, badge/boolean/date columns, select/ternary filters, default sort, row + bulk actions; relationships.
- **nav:** navigation groups, icons, sort; command palette.
- **auth:** login/register/forgot/reset/verify pages wired.
- **tenant:** multi-tenant Resource scoped via `BelongsToTenant`, `<TenantSwitcher>` (using the now-native `tenant` prop + `arqel.resources.discover` and the config-driven resolver from the prior sprint).
- **widgets:** a dashboard with Stat/Chart/Table/Custom widgets.
- **export:** CSV/Excel export action on a Resource.
- **audit:** activity logging on a Resource (opt-in dep).
- **ai:** an AI-powered field/feature where the package supports it (stub provider — no real API cost).
- **realtime:** a realtime-updated list (Echo wiring; degrade gracefully when no Echo server).
- **versioning:** a versioned Resource.
- **workflow:** a Resource with a state machine / workflow.
- **mcp:** the MCP server registered (smoke only).
- **marketplace:** wired if it cleanly applies; otherwise documented as out-of-scope for the showcase with a reason.
- **cli:** exercised via the scaffolding/install commands used to build the app.

**E2E (Playwright):** specs covering the visual + functional flows — list/create/edit/delete/filter/sort/search/bulk for a couple of Resources, dashboard render, tenant switch, export download, command palette. Uses `127.0.0.1` host (the IPv6 gotcha) and a smoke-check CI step.

**Acceptance for Phase 0:** the showcase boots, `php artisan test` green, typecheck green, E2E green, and `pnpm -r build` includes it. The showcase itself must be CORRECT — a buggy showcase produces false-positive framework bugs. Phase 0 ships as its own PR (reviewed, CI clean, merged) before the loop runs, so the loop operates on a known-good baseline on `main`.

**CI:** extend the `e2e` job to run `apps/showcase` alongside demo/tenant-demo.

---

## The autonomous loop (until 2 clean rounds)

Operates on `main` after Phase 0 is merged. Implemented as a `Workflow` script so control flow (fan-out, pipeline, loop, seen-registry) is deterministic.

### Round structure

**1. Detection (fan-out, barrier per round).** N detection agents, one per package-domain cluster, each blind to the others (multi-modal sweep). Clusters:
- `core/fields/fields-advanced/form/table/actions`
- `tenant/auth/audit/nav`
- `ai/realtime/workflow/versioning`
- `widgets/export/mcp/marketplace/cli`

Each agent runs, against the showcase: `composer install` + `php artisan test`, `tsc`/typecheck, the relevant E2E specs, plus exploratory usage of its packages' public APIs. Output (structured): candidate bugs with `{package, layer, title, evidence (file:line / failing assertion / screenshot note), suspected severity}`.

**2. Dedup + severity + seen-filter (short barrier).** Merge duplicates across agents/layers (same root cause surfacing in Pest + E2E counts once). Drop candidates already in the persistent seen-registry (`docs/superpowers/reports/dogfood-seen.json` — keyed by a stable signature). Assign severity blocker/high/medium/low. → prioritized queue.

**3. Per-bug pipeline (parallel, serialized per package).** Each queued bug flows independently through:
- **Adversarial verify + classify.** A skeptic agent tries to REFUTE the bug AND classifies it: `framework-bug` vs `app-misuse` (the showcase used the API wrong). App-misuse → fix the showcase code (no GitHub issue). Framework-bug confirmed (skeptic could not refute) → open a GitHub issue (`gh issue create`, labels `bug` + `package: <x>` + a severity note in the body).
- **Triage + plan.** Study the owning package, find root cause, write a short TDD action plan (which file, what failing test proves it, the fix).
- **Fix (TDD + two-stage review).** A fresh implementer writes a regression test that FAILS before the fix and passes after, implements the minimal fix following project conventions (`declare(strict_types=1)`, final-by-default, DCO commits, scoped pest), then a spec-compliance review and an adversarial code-quality review. Update docs/SKILL.md/CHANGELOG when the fix changes behavior or a public API.
- **Publish.** Open a PR referencing the issue (`Closes #N`), watch CI to `CLEAN` (if a new dependency advisory appears, add a pnpm override — established pattern), rebase-merge with `--delete-branch`, the issue auto-closes, and record the bug's signature in the seen-registry.

**Per-package serialization:** at most one in-flight fix PR per package at a time (avoid merge conflicts); different packages proceed in parallel. The Workflow groups the pipeline by `package` and runs same-package bugs sequentially.

**4. Re-detect.** A fresh detection round runs against the now-updated `main`. If it finds zero new (non-seen) framework bugs for **2 consecutive rounds**, stop.

### Safeguards (autonomous total)
- CI `mergeStateStatus == CLEAN` required before every merge. Never `--no-verify`, never force-push, never `--admin`.
- Per-package single-in-flight-PR serialization.
- Persistent seen-registry → loop converges, never re-files a fixed bug.
- Per-round bug cap and total agent cap as runaway backstops.
- App-misuse never becomes a framework issue.

### Deliverables
- `apps/showcase` merged to `main` (Phase 0 PR).
- One GitHub issue per confirmed framework bug, auto-closed by its fix PR.
- One fix PR per framework bug — each with a regression test (fails-before) + docs updates where behavior changed — merged on CLEAN CI.
- A final report (`docs/superpowers/reports/2026-06-05-dogfood-ecosystem-report.md`): bugs found/fixed per package, app-misuses corrected, what (if anything) remains open and why.

---

## Risks & rollout
- **Scaffold risk:** a wrong showcase yields false positives. Mitigated by Phase 0 acceptance gates (green tests/E2E) and the framework-vs-misuse classifier.
- **Cost:** large. Mitigated by dedup, seen-registry, per-package serialization, and the 2-clean-round stop.
- **Merge safety:** CLEAN-only merges + per-package serialization + no force-push.
- **Out-of-scope packages:** if a package can't be cleanly exercised in a single app (e.g. marketplace needs Stripe keys → cost), it's documented as out-of-scope with a reason rather than faked.
- No new release/tag is cut by the loop; version bump is a separate human-triggered sprint.

# Dogfood i18n Loop — Final Report (CONVERGED)

**Converged:** 2026-06-25, after **2 consecutive integral clean rounds (R40 + R41)**.

## Summary

An autonomous detect→verify→fix→PR→CI→merge loop targeting **i18n / localization**
defects across the Arqel monorepo. Ran **41 rounds total** (34 with fixes,
7 clean), landing **366 confirmed-and-fixed i18n bugs** across **PRs #271–#305**
(+ this report). Stop criterion: 2 consecutive rounds where the exhaustive
3-dimension sweep (aria/visible-text-JS, PHP user-facing, locale-format),
adversarially verified (refute-by-default) and deduped against the growing
`reports/i18n-fixed-ledger.json`, confirms **0** new bugs — both rounds
*integral* (all 3 detection dimensions completed, no API rate-limit truncation).

## Detection curve (confirmed per round, R1→R41)

53, 21, 14, 18, 9, 18, 33, 20, 31, 12, 19, 13, 7, 3, 14, 4, 3, **0**, 10, 13,
9, **0**, 1, 8, **0**, 3, **0**, 3, 1, 4, 6, **0**, 5, 4, 1, 1, 2, 1, 2, **0**, **0**

The curve is non-monotonic: each exhaustive sweep periodically *reached a
component/package/family it had not yet covered* and surfaced the whole family
at once (pre-existing bugs, not new ones). False convergences (R18, R22, R25,
R27, R32) were single clean rounds broken by the next sweep reaching an
unswept sub-tree — only the **integral 2-in-a-row (R40+R41)** is real
convergence.

## "Drained families" (whole-family fixes)

- R7 fields-advanced editor aria (33) · R15 BuilderInput per-block aria (14)
- R17 the 5 marketplace inline-validate controllers (systemic, one pass)
- R19+R20+R21 the entire devtools-extension panel set → **self-contained
  `src/panel/i18n.ts`** (no Inertia ctx; locale from `navigator.language`)
- R24 cli-ink TUI → **self-contained `src/i18n.ts`** (locale from `process.env.LANG`)
- R28 all 6 chart widgets axis/tooltip/slice number-format
- R36 built-in view/edit/restore/create action factory labels (R16 did deleteBulk)
- R37 widgets dashboard Filter/SelectFilter label localization

## Scope decisions (user)

- **devtools-extension** (R19) and **cli-ink** (R24): dev/operator-facing, no
  i18n wiring → user chose *full i18n* via self-contained modules.
- **Out of scope** (deliberate, recorded in ledger): showcase `GridFormDemo`
  heading (E2E scaffold fixture — localizing it broke `responsive-forms` E2E,
  reverted); `PublisherProfile` "Downloads" label (standard pt-BR loanword);
  `PerformanceMetrics` toFixed Web-Vitals unit values; cli-ink `meow --help` banner.

## Coverage

All user-facing surfaces localized: admin UI (table/form/fields/actions/nav/
widgets/auth/tenant), AI fields, marketplace app + controllers, versioning,
realtime, audit, export, **devtools-extension** + **cli-ink** (self-contained),
chart widgets, CRUD page titles, breadcrumbs, pagination, validation messages,
date/number/currency/plural locale formatting, and the `arqel::`-namespaced
PHP lang catalogs (en + pt_BR, with correct diacritics) kept at parity.

## Artifacts

- `reports/i18n-fixed-ledger.json` — 366 sigs (file/line/title/round)
- `reports/i18n-round{1..41}-detection.json` — per-round detection
- PRs #271–#305 (squash-merged, each green CI)

## Recurring CI gotchas (saved to memory)

Biome lints `**/*.json` + new test files (format before PR) · commitlint
scope-enum excludes `i18n`; `revert` is not a valid type; subject ≤100 ·
cli-ink async-load tests used fixed 10ms delays → flaky under workspace-wide
Vitest (bumped to 200ms) · E2E Docker-Hub image-pull flake (rerun) · i18n on
an app page can break an untested-locally E2E (run showcase E2E or treat as
out-of-scope) · `getLabel()` returns the raw key; assert `toArray()['label']`.

# Spec — Responsive-Design Dogfood Loop (Round 1, design/layout/responsiveness)

> **Status:** approved design (2026-06-11). Next: writing-plans → implementation.
> **Initiative:** a fresh dogfooding cycle, like the functional Rounds 22/23, but the defect category is **layout/responsiveness** instead of functional/security bugs. The product is **improved responsiveness of the `@arqel-dev/*` framework packages**; `apps/showcase` is the evaluation stage.

## 1. Goal & scope

Find and fix **objective responsiveness defects** in the Arqel framework's UI packages, surface by surface, via an autonomous detect → verify → issue → TDD-fix → double-review → PR → merge loop (the same pipeline as Rounds 22/23).

- **Target of fixes:** the `@arqel-dev/*` packages (primarily `packages-js/ui`, plus `packages-js/react`/`theme` for tokens/typography). The `apps/showcase` app is where each surface is rendered + measured; showcase changes are limited to exposing surfaces on stable routes for measurement.
- **In scope:** horizontal overflow, clipped/off-viewport content, touch-target size, and layout reflow across target viewports — using **shadcn + Tailwind responsive utilities + `cva`**, consuming the existing OKLCH design tokens.
- **OUT of scope (YAGNI):** aesthetic redesign, a custom theme, new dark-mode work, animations, new components that don't serve responsiveness, and general accessibility beyond touch-target size (a11y is a separate initiative).

### Hard constraint — shadcn + project patterns
Every fix MUST use shadcn (ShadCN CLI v4 + Radix) primitives and the project's established patterns — **no ad-hoc CSS, no hand-rolled components** when a shadcn primitive or an existing convention covers it. New responsive variants use Tailwind responsive utilities (`sm:`/`md:`/`lg:`) + `cva` in the style of the existing shadcn components; consume the existing OKLCH tokens (`packages-js/ui/src/styles/globals.css` + `@theme inline`), don't invent color/spacing systems. If a needed shadcn primitive isn't installed, add it via the ShadCN CLI rather than writing it by hand. This is verified at review.

## 2. Target viewports (5 tiers)

The loop drives each surface in the showcase at these widths (chosen to match the project's real Tailwind v4 breakpoints + the shadcn `useIsMobile` 768px drawer threshold):

| width | tier | exercises |
|---|---|---|
| 360 | small phone | common Android; tightest reflow |
| 640 | `sm` | Tailwind `sm` breakpoint |
| 768 | `md` | sidebar↔drawer switch (`useIsMobile`) |
| 1024 | `lg` | Tailwind `lg` breakpoint |
| 1440 | wide desktop | no-regression baseline (with 1280 as the desktop reference too) |

## 3. Surfaces (6) + current state (from the code exploration)

| surface | current state | primary gap |
|---|---|---|
| **Forms** (FormRenderer / FormGrid / FormTabs) | desktop-first | static columns (no mobile 1-col); `FormGrid` accepts a responsive `{sm,md,lg}` map but applies only one value; `FormTabs` has no mobile variant (overflows) |
| **Typography + spacing** (cross-cutting) | gap | `2xl` titles don't shrink on mobile; no responsive type scale; inconsistent spacing rhythm |
| **Tables** (DataTable / TableToolbar / TableFilters / TablePagination) | partial | only horizontal-scroll + `hiddenOnMobile` column-hiding; no card/stacked mobile view; toolbar/filters don't stack |
| **Shell** (Topbar / Sidebar / MainContent) | partial | Sidebar drawer (shadcn) is solid; Topbar only hides search at `md` (actions always visible → can cramp) |
| **Modals/dialogs/dropdowns** (ConfirmDialog / ActionFormModal / ActionMenu) | to verify | width / internal scroll / touch on mobile unverified |
| **Dashboard/widgets** (DashboardGrid / StatCard / charts) | base ok | grid columns already responsive; verify StatCard/Recharts overflow + resize at small widths |

## 4. Loop architecture (per surface, autonomous)

Mirrors Rounds 22/23. For each surface:

1. **DETECT** — Playwright drives the surface in the showcase at 360/640/768/1024/1440 and **measures the DOM** (§5). Each failing measurement = a candidate defect. Capture a screenshot per width.
2. **VERIFY** — adversarially confirm it's a real framework defect (the shared component is the cause) vs showcase misuse. User sees the screenshots.
3. **ISSUE** — GitHub issue with the viewport + the failing metric + the screenshot.
4. **TDD FIX** — write the failing responsive E2E first (RED at the small width), implement the fix in the shared component (shadcn/Tailwind/cva), GREEN. The test asserts **three** things: (a) mobile now passes; (b) desktop (1280/1440) does NOT regress; (c) the whole responsive suite stays green (shared-component cross-regression).
5. **DOUBLE REVIEW** — spec-compliance + quality, explicitly including: "this component is shared — confirm consumers X/Y/Z don't regress" and "shadcn/tokens only, no ad-hoc CSS, no aesthetic change."
6. **PR → CI-clean → merge → before/after screenshots → next surface.**

### Key difference vs the bug cycle
Fixes land in **shared components** (e.g. making `FormGrid` responsive affects every form). So verification has a dual obligation: **mobile passes AND desktop / other consuming surfaces don't regress.** The E2E encodes both, and the full responsive suite runs in CI on every shared-component fix.

## 5. Detection engine (Playwright + DOM measurement)

Extends the existing `apps/showcase/tests/e2e/` infra (dual-mode `APP_BASE_URL`, `loggedInPage` fixture, dogfood Docker stack). A shared helper `apps/showcase/tests/e2e/responsive.ts` provides the assertion vocabulary:

- `forEachViewport(page, [360,640,768,1024,1440], fn)` — set viewport, wait for `networkidle` + fonts loaded, run `fn`.
- `assertNoHorizontalOverflow(page, selector)` — `scrollWidth <= clientWidth` for `<body>` and each root container of the surface.
- `assertWithinViewport(page, selectors)` — each key element's bounding box within `[0, viewportWidth]`; nothing with `right > viewportWidth` or `width === 0`.
- `assertTouchTargets(page, { minPx: 44, widths: [360,640] })` — every interactive element (`button`, `a`, `[role=button]`, inputs, tab triggers) has `min(width,height) >= 44` at the mobile widths (WCAG 2.5.5 / Apple HIG).
- Per-surface **reflow assertions** (concrete, not "looks good"): Form Grid → 1 column at 360; Table → card/stacked view (or acceptable column-hiding); Tabs → scrollable/dropdown; Topbar → actions collapse, no cramp.

**Screenshots:** each spec captures `screenshot({ fullPage })` per width to `test-results/responsive/<surface>-<width>.png` (the metric is the gate; the screenshot is the human evidence — used for the user's before/after approval).

**Determinism:** measure against the dogfood stack with **fresh dist** (rebuild JS packages before measuring — the stale-dist lesson from prior rounds); the fixture resets the DB per test; wait for fonts + `networkidle` before measuring; `retries:1`.

## 6. Fix strategy (shadcn-first) — per surface

- **Forms:** `FormGrid` applies the responsive map it already accepts (`{sm,md,lg}` → `grid-cols-1 md:grid-cols-2 lg:grid-cols-3`, mirroring `DashboardGrid` which already does this correctly); mobile default = 1 column. `FormTabs` gets a mobile variant via a shadcn/Radix Tabs primitive (scrollable tab bar or collapse), not a custom build.
- **Typography:** a responsive type scale via tokens/`cva` (titles shrink on mobile), applied consistently across surfaces, using the existing OKLCH tokens + `@theme`. Done early (Phase 2) because it's cross-cutting.
- **Tables:** `DataTable` gains a **card/stacked view** below `md` (each row → a label→value card), with the existing column-hiding as complement; toolbar/filters stack via Tailwind responsive utilities.
- **Shell:** `Topbar` actions that cramp on mobile collapse into a shadcn DropdownMenu/Sheet, reusing the project's pattern.
- **Modals:** shadcn `Dialog`/`Sheet` — full-bleed width + internal scroll on mobile; touch-sized controls.
- **Dashboard:** verify/fix `StatCard`/chart overflow (Recharts `ResponsiveContainer`) at small widths.

No aesthetic change: colors, visual identity, and density stay — only responsive behavior changes. A fix that *tries* to change aesthetics is rejected at review (out of scope).

## 7. Phasing & order (largest gap → smallest)

- **Phase 0 — measurement infra (once):** create `responsive.ts`; ensure each surface has a stable showcase route (add one if a surface needs isolation, e.g. a tabs-free form to isolate the Grid); run the **baseline** detection (6 surfaces × 5 widths) → an initial defect report + screenshots (the loop's map, like the prior rounds' coverage report). Non-blocking — it's the starting point.
- **Phase 1 — Forms** (largest gap; `FormGrid` is the most-repeated pattern).
- **Phase 2 — Typography/spacing** (cross-cutting; fixed early to avoid rework downstream).
- **Phase 3 — Tables** (card view).
- **Phase 4 — Shell/Topbar.**
- **Phase 5 — Modals/dialogs.**
- **Phase 6 — Dashboard/widgets** (mostly verification).

Each phase: detect → issue(s) → TDD fix(es) → double-review → PR → merge CI-clean → before/after screenshots → next.

**Convergence:** a surface "closes" when it passes all 4 assertions at all 5 widths AND desktop doesn't regress. The initiative closes when all 6 surfaces are green at all widths.

## 8. CI / process

- Reuses the dogfood Docker stack + the existing E2E-on-Docker CI job (`retries:1`); the responsive specs join the showcase E2E suite.
- CI-clean required before every merge; PRs/merges via the `curl --resolve api.github.com:443:140.82.112.6` + `gh auth token` workaround while the GitHub API DNS is broken on this host (git-over-SSH works).
- Rebuild fresh dist before measuring/testing.
- Commit scopes per the project: `demo` for showcase/test changes (commitlint rejects `showcase`), the package name (`ui`/`react`/`form`/`table`/...) for framework fixes. DCO `--signoff`; `--no-verify` only when the broken-node_modules husky hook blocks, after verifying biome (container) + the relevant tests. Subjects ≤100 chars.

## 9. Acceptance criteria (per surface)

- ✅ 0 horizontal overflow across the 5 widths;
- ✅ 0 clipped / off-viewport elements;
- ✅ all touch targets ≥44px at 360/640;
- ✅ reflows per the surface's expectation (Grid 1-col mobile, table card-view, tabs scrollable, topbar collapses);
- ✅ desktop (1280/1440) + other surfaces using the touched components do NOT regress;
- ✅ every fix uses shadcn/Tailwind/cva + existing tokens — zero ad-hoc CSS, zero hand-rolled component (review-verified);
- ✅ before/after screenshots attached.

## 10. Risks & mitigations

1. **Cross-regression** (shared-component fix breaks another surface) → the full responsive suite runs in CI + an explicit "consumers of this component" review check.
2. **Stale dist** (measuring old CSS) → rebuild fresh dist before measuring.
3. **Measurement flakiness** (layout shift, font load) → wait for `networkidle` + fonts before measuring; `retries:1`.
4. **"Reflows" subjectivity** → each surface defines concrete reflow assertions (not "looks good"), keeping the gate objective.
5. **Scope creep into aesthetics** → review rejects color/identity/density changes; responsive behavior only.
6. **Missing shadcn primitive** (e.g. scrollable tabs) → install via the ShadCN CLI, don't hand-write.

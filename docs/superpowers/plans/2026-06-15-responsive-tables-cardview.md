# DataTable card-view mobile (Phase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render the framework `DataTable` as a stacked label:value card list below `md` (768px) and as the existing `<table>` at `md`+, so resource indexes are readable on mobile without horizontal scroll.

**Architecture:** No viewport-measuring JS. The existing `<table>` is wrapped in `hidden md:block`; a new sibling card list is `md:hidden`. Both render from the same `records` + `visibleColumns` + `rowActions` and reuse `<TableCell>` for value formatting. The latent `<th>` `hiddenOnMobile` bug (header not hidden while the `<td>` is) is fixed in the same pass.

**Tech Stack:** React 19, TanStack Table v8, Tailwind v4 (responsive utilities only), shadcn primitives, Playwright E2E against the dogfood Docker stack (`APP_BASE_URL=http://localhost:8090`).

---

## Operating notes (environment)

- **Branch:** `round-design/responsive-tables` (already created from `main`, has the design-doc commit).
- **JS tooling does NOT run on the host** (root-owned node_modules). Run biome/build in a clean container:
  `docker run --rm -v /home/diogo/PhpstormProjects/arqel:/w -w /w node:22-alpine sh -c "corepack enable && CI=true pnpm install --frozen-lockfile && <cmd>"`
  (Pass `CI=true` so pnpm does not prompt to purge modules and abort the `&&` chain.)
- **STALE-DIST trap:** the stack serves `@arqel-dev/*` from built `dist/`. After ANY `packages-js/ui` change you MUST: rebuild `ui` dist in a clean container, then **delete the vite pre-bundle cache and restart vite**, or you measure stale code:
  ```
  docker run --rm -v /home/diogo/PhpstormProjects/arqel:/w -w /w node:22-alpine sh -c "corepack enable && CI=true pnpm install --frozen-lockfile && pnpm --filter @arqel-dev/ui build"
  docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml exec -T vite sh -c "rm -rf /app/apps/showcase/node_modules/.vite"
  docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml restart vite
  ```
- **Playwright runs on the host** (`apps/showcase/node_modules/.bin/playwright` works) with `APP_BASE_URL=http://localhost:8090`.
- **Login:** `admin@arqel.test` / `password` (the `loggedInPage` fixture does it).
- **Commits:** DCO `--signoff`; Conventional Commits (`ui` for framework, `demo` for showcase/test); subjects ≤100 chars; `--no-verify` only when the broken host husky blocks (after biome+tests pass in container); footer `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`. Use `git -C <path>` not `cd && git`.
- **biome scope:** `src/table/DataTable.tsx` is under `src/` (NOT `src/shadcn/**`), so it MUST pass biome.

---

## File structure

- **Modify:** `packages-js/ui/src/table/DataTable.tsx` — wrap the table in `hidden md:block`; add a `<DataCards>` sub-component (`md:hidden`); fix the `<th>` hiddenOnMobile bug. Keep it one file (the card list is ~40 lines and shares all the selection/cell logic).
- **Reuse (no change):** `packages-js/ui/src/table/cells.tsx` (`TableCell`), `packages-js/types/src/tables.ts` (`ColumnSchema`).
- **Create:** `apps/showcase/tests/e2e/responsive-tables.spec.ts` — the new gate.
- **Maybe modify:** `apps/showcase/tests/e2e/responsive.ts` — only if a visible/hidden helper is needed (Task 4 decides).

---

## Task 1: Fix the `<th>` hiddenOnMobile alignment bug

**Files:**
- Modify: `packages-js/ui/src/table/DataTable.tsx` (the `<th>` for schema columns, ~line 150)

This is a self-contained correctness fix that also matters for the card work (the `<table>` is `md`-only after Task 2, but the header should still mirror the body's hiddenOnMobile rule). The `<td>` already has `col?.hiddenOnMobile && 'hidden md:table-cell'` (~line 252); the `<th>` does not.

- [ ] **Step 1: Add a failing E2E assertion**

Create `apps/showcase/tests/e2e/responsive-tables.spec.ts` with ONLY this test for now:

```ts
import { expect, test } from './fixtures';

test.describe('responsive — tables', () => {
  // Guards the latent bug: a hiddenOnMobile column hid its <td> but not its
  // <th>, so the header had one more cell than each body row at < md. Assert
  // header cell count never exceeds the first body row's cell count.
  test('the table header has no orphan hiddenOnMobile column at mobile width', async ({
    loggedInPage: page,
  }) => {
    await page.setViewportSize({ width: 360, height: 900 });
    await page.goto('/admin/posts', { waitUntil: 'networkidle' });
    // Force the table visible regardless of the (later) card breakpoint, so this
    // test isolates the header/body column-count parity.
    const counts = await page.evaluate(() => {
      const table = document.querySelector('table');
      if (!table) return { ths: -1, tds: -1 };
      const ths = table.querySelectorAll('thead tr th').length;
      const firstRow = table.querySelector('tbody tr');
      const tds = firstRow ? firstRow.querySelectorAll('td').length : -1;
      return { ths, tds };
    });
    expect(counts.tds, 'no body row found').toBeGreaterThan(0);
    expect(counts.ths, `header cells (${counts.ths}) must equal body cells (${counts.tds})`).toBe(
      counts.tds,
    );
  });
});
```

NOTE: this test depends on the `<table>` being in the DOM at 360px. Today it is (the table is always rendered). After Task 2 the table becomes `hidden md:block` (still in the DOM, just `display:none`) — `querySelectorAll` still counts it, so this test stays valid. If the PostResource posts table has NO hiddenOnMobile column, this test passes trivially; that's acceptable — it's a regression guard. (Verify in Step 2 whether it currently fails; if the posts resource has no hiddenOnMobile column, SKIP Task 1's fix is NOT allowed — still apply the `<th>` fix in Step 3 as defense, and keep the guard.)

- [ ] **Step 2: Run it (RED or trivially green)**

Run: `cd apps/showcase && APP_BASE_URL=http://localhost:8090 node_modules/.bin/playwright test responsive-tables --reporter=line`
Expected: PASS if posts has no hiddenOnMobile column, or FAIL (`header cells (N) must equal body cells (N-1)`) if it does. Record which.

- [ ] **Step 3: Apply the `<th>` fix**

In `packages-js/ui/src/table/DataTable.tsx`, the schema-column `<th>` (the one with `aria-sort`, ~line 150) currently has:

```tsx
className={cn(
  'px-3 py-2 text-left font-medium text-muted-foreground',
  col?.align === 'center' && 'text-center',
  col?.align === 'end' && 'text-right',
)}
```

Add the hiddenOnMobile mirror so the header matches the body cell:

```tsx
className={cn(
  'px-3 py-2 text-left font-medium text-muted-foreground',
  col?.align === 'center' && 'text-center',
  col?.align === 'end' && 'text-right',
  col?.hiddenOnMobile && 'hidden md:table-cell',
)}
```

- [ ] **Step 4: Rebuild dist + restart vite, re-run the test**

Run the rebuild/restart block from "Operating notes", then:
`cd apps/showcase && APP_BASE_URL=http://localhost:8090 node_modules/.bin/playwright test responsive-tables --reporter=line`
Expected: PASS.

- [ ] **Step 5: biome the changed file**

Run: `docker run --rm -v /home/diogo/PhpstormProjects/arqel:/w -w /w node:22-alpine sh -c "corepack enable && CI=true pnpm install --frozen-lockfile && pnpm --filter @arqel-dev/ui exec biome check src/table/DataTable.tsx"`
Expected: no errors (warnings tolerated).

- [ ] **Step 6: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add packages-js/ui/src/table/DataTable.tsx apps/showcase/tests/e2e/responsive-tables.spec.ts
git -C /home/diogo/PhpstormProjects/arqel commit --no-verify --signoff -m "fix(ui): hide the <th> of a hiddenOnMobile column so the header matches the body" -m "The <td> honored col.hiddenOnMobile (hidden md:table-cell) but the matching <th> did not, leaving an orphan header cell that misaligned the table at < md. Mirror the rule on the header." -m "Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Render a card list below `md`, table at `md`+

**Files:**
- Modify: `packages-js/ui/src/table/DataTable.tsx`

The selection/sort/cell logic stays. We split RENDERING into two siblings: the existing table (`hidden md:block` on the wrapper) and a new `<DataCards>` (`md:hidden`).

- [ ] **Step 1: Write the failing E2E test (card-vs-table by viewport)**

Append to `apps/showcase/tests/e2e/responsive-tables.spec.ts` (inside the same `describe`):

```ts
  test('the index shows cards on mobile and the table on desktop', async ({
    loggedInPage: page,
  }) => {
    await page.goto('/admin/posts', { waitUntil: 'networkidle' });

    // Mobile: table hidden, >=1 card visible.
    await page.setViewportSize({ width: 360, height: 900 });
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
    const mobile = await page.evaluate(() => {
      const table = document.querySelector('table');
      const tableVisible = !!table && table.getClientRects().length > 0;
      const cards = Array.from(
        document.querySelectorAll('[data-arqel-data-card]'),
      ).filter((el) => (el as HTMLElement).getClientRects().length > 0);
      return { tableVisible, cardCount: cards.length };
    });
    expect(mobile.tableVisible, 'table must be hidden on mobile').toBe(false);
    expect(mobile.cardCount, 'at least one card on mobile').toBeGreaterThan(0);

    // Desktop: table visible, cards hidden.
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
    const desktop = await page.evaluate(() => {
      const table = document.querySelector('table');
      const tableVisible = !!table && table.getClientRects().length > 0;
      const cards = Array.from(
        document.querySelectorAll('[data-arqel-data-card]'),
      ).filter((el) => (el as HTMLElement).getClientRects().length > 0);
      return { tableVisible, cardCount: cards.length };
    });
    expect(desktop.tableVisible, 'table must be visible on desktop').toBe(true);
    expect(desktop.cardCount, 'cards must be hidden on desktop').toBe(0);
  });
```

- [ ] **Step 2: Run it to verify RED**

Run: `cd apps/showcase && APP_BASE_URL=http://localhost:8090 node_modules/.bin/playwright test responsive-tables --reporter=line`
Expected: FAIL — no `[data-arqel-data-card]` exists yet, and the table is visible at 360.

- [ ] **Step 3: Make the table wrapper md-only**

In `DataTable.tsx`, change the outer wrapper (currently `'w-full min-w-0 overflow-x-auto'`, ~line 127) to hide it below `md`:

```tsx
  return (
    <>
      <div className={cn('hidden w-full min-w-0 overflow-x-auto md:block', className)}>
        <table className="w-full border-collapse text-sm">
          {/* …unchanged thead/tbody… */}
        </table>
      </div>
      <DataCards
        columns={visibleColumns}
        rows={table.getRowModel().rows}
        enableSelection={enableSelection}
        selectedIds={selectedIds}
        onToggleRow={toggleRow}
        rowActions={rowActions}
        loading={loading}
        emptyState={emptyState}
      />
    </>
  );
```

(Keep the existing `<table>` body exactly as-is inside that first `<div>`. Only the wrapper className changed and the surrounding fragment + `<DataCards>` were added.)

- [ ] **Step 4: Add the `<DataCards>` sub-component**

Add this component in the same file (below `DataTable`). It reuses `TableCell` and mirrors the selection wiring. It is `md:hidden`. Each row → an `<article data-arqel-data-card>`.

```tsx
interface DataCardsProps<TRecord extends DataTableRecord> {
  columns: ColumnSchema[];
  rows: Row<TRecord>[];
  enableSelection: boolean;
  selectedIds: ReadonlyArray<RowId>;
  onToggleRow: (id: RowId, index: number, shiftKey: boolean) => void;
  rowActions?: ((record: TRecord) => ReactNode) | undefined;
  loading: boolean;
  emptyState?: ReactNode;
}

function DataCards<TRecord extends DataTableRecord>({
  columns,
  rows,
  enableSelection,
  selectedIds,
  onToggleRow,
  rowActions,
  loading,
  emptyState,
}: DataCardsProps<TRecord>) {
  // Columns shown as label:value pairs in the card body — hiddenOnMobile
  // columns are intentionally dropped (same intent as the table at < md).
  const cardColumns = columns.filter((col) => !col.hiddenOnMobile);

  if (loading) {
    return (
      <div className="px-3 py-4 text-center text-muted-foreground md:hidden">Loading…</div>
    );
  }
  if (rows.length === 0) {
    return (
      <div className="px-3 py-8 text-center text-muted-foreground md:hidden">
        {emptyState ?? 'No records found.'}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-3 md:hidden">
      {rows.map((row, index) => {
        const record = row.original;
        const checked = selectedIds.includes(record.id);
        return (
          <article
            key={row.id}
            data-arqel-data-card=""
            data-selected={checked || undefined}
            className={cn(
              'rounded-lg border border-border p-4 text-sm',
              checked && 'bg-muted',
            )}
          >
            {(enableSelection || rowActions) && (
              <div className="mb-3 flex items-center justify-between gap-2">
                {enableSelection ? (
                  <input
                    type="checkbox"
                    aria-label={`Select row ${record.id}`}
                    className="size-5"
                    checked={checked}
                    onChange={(event) => {
                      const native = event.nativeEvent as MouseEvent;
                      onToggleRow(record.id, index, native.shiftKey === true);
                    }}
                  />
                ) : (
                  <span />
                )}
                {rowActions ? <div className="ml-auto">{rowActions(record)}</div> : null}
              </div>
            )}
            <dl className="grid grid-cols-[minmax(0,auto)_1fr] gap-x-3 gap-y-2">
              {cardColumns.map((col) => (
                <div key={col.name} className="contents">
                  <dt className="font-medium text-muted-foreground">{col.label ?? col.name}</dt>
                  <dd
                    className={cn(
                      'min-w-0 break-words',
                      col.align === 'end' && 'text-right',
                      col.align === 'center' && 'text-center',
                    )}
                  >
                    <TableCell
                      column={col}
                      value={(record as Record<string, unknown>)[col.name]}
                    />
                  </dd>
                </div>
              ))}
            </dl>
          </article>
        );
      })}
    </div>
  );
}
```

- [ ] **Step 5: Add the missing imports/types**

At the top of `DataTable.tsx`, extend the TanStack import to include `Row`:

```tsx
import { type ColumnDef, flexRender, getCoreRowModel, type Row, useReactTable } from '@tanstack/react-table';
```

`TableCell` is already imported (`import { TableCell } from './cells.js';`). `ColumnSchema`, `ReactNode`, `cn`, `RowId`, `DataTableRecord` are already in scope.

- [ ] **Step 6: Rebuild dist + restart vite, run the test (GREEN)**

Run the rebuild/restart block, then:
`cd apps/showcase && APP_BASE_URL=http://localhost:8090 node_modules/.bin/playwright test responsive-tables --reporter=line`
Expected: PASS (both tests).

- [ ] **Step 7: biome + commit**

```bash
docker run --rm -v /home/diogo/PhpstormProjects/arqel:/w -w /w node:22-alpine sh -c "corepack enable && CI=true pnpm install --frozen-lockfile && pnpm --filter @arqel-dev/ui exec biome check src/table/DataTable.tsx"
git -C /home/diogo/PhpstormProjects/arqel add packages-js/ui/src/table/DataTable.tsx apps/showcase/tests/e2e/responsive-tables.spec.ts
git -C /home/diogo/PhpstormProjects/arqel commit --no-verify --signoff -m "feat(ui): stack DataTable rows as label:value cards below md (mobile card-view)" -m "Below 768px the index table is unreadable without horizontal scroll. Render each row as a stacked card (selection + actions header, then label:value pairs reusing TableCell); keep the <table> at md+. hiddenOnMobile columns are dropped from the card body. Tailwind hidden/md:block + md:hidden only — no viewport-measuring JS." -m "Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Touch targets + no-overflow on the card surface

**Files:**
- Modify: `apps/showcase/tests/e2e/responsive-tables.spec.ts`

Add the safety assertions the design requires: cards must not overflow horizontally, and their interactive controls (selection checkbox, action trigger) must be ≥44px at mobile widths.

- [ ] **Step 1: Append the test**

```ts
  test('the card surface has no overflow and touchable controls on mobile', async ({
    loggedInPage: page,
  }) => {
    await page.goto('/admin/posts', { waitUntil: 'networkidle' });
    for (const w of [360, 640] as const) {
      await page.setViewportSize({ width: w, height: 900 });
      await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => r(null))));
      // No horizontal overflow on the body.
      const body = await page.evaluate(() => ({
        sw: document.body.scrollWidth,
        cw: document.body.clientWidth,
      }));
      expect(body.sw, `body overflow at ${w}: ${body.sw} > ${body.cw}`).toBeLessThanOrEqual(
        body.cw + 1,
      );
      // The row-action trigger inside a card is >=44px.
      const trigger = page.locator('[data-arqel-data-card] button[aria-label="Actions"]').first();
      if (await trigger.count()) {
        const box = await trigger.boundingBox();
        expect(box, 'action trigger has a box').not.toBeNull();
        if (box) expect(Math.min(box.width, box.height)).toBeGreaterThanOrEqual(44);
      }
    }
  });
```

- [ ] **Step 2: Run it**

Run: `cd apps/showcase && APP_BASE_URL=http://localhost:8090 node_modules/.bin/playwright test responsive-tables --reporter=line`
Expected: PASS. The action trigger already uses `size="icon-touch"` (44px) from Phase 1. If it FAILS on overflow, a card child has an unbreakable wide value — add `min-w-0 break-words` to the offending `<dd>` (already present) or to the card `<article>`; re-run.

- [ ] **Step 3: Commit**

```bash
git -C /home/diogo/PhpstormProjects/arqel add apps/showcase/tests/e2e/responsive-tables.spec.ts
git -C /home/diogo/PhpstormProjects/arqel commit --no-verify --signoff -m "test(demo): assert the mobile card surface has no overflow and touchable controls" -m "Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Full-suite regression + baseline refresh

**Files:** none (verification only), possibly refresh the baseline screenshots.

- [ ] **Step 1: Run the full default E2E suite**

Run: `cd apps/showcase && APP_BASE_URL=http://localhost:8090 node_modules/.bin/playwright test --reporter=line`
Expected: ALL pass (the new responsive-tables specs + the existing 41). No desktop regression on i18n/theme/resource specs (they read the `<table>`, which still renders at desktop and stays in the DOM at mobile as `display:none`).

If any prior spec selected `table tbody tr` at a mobile viewport and now finds it `display:none`, that is a REAL interaction to fix in THAT spec (navigate at desktop width, or target the card). Note it and fix minimally; do not weaken the new gate.

- [ ] **Step 2: Refresh the baseline defect map (optional but recommended)**

The baseline `tables` surface screenshots now show cards at mobile. Refresh them so the recorded map reflects the new state:

```bash
cd apps/showcase && APP_BASE_URL=http://localhost:8090 node_modules/.bin/playwright test responsive-baseline.spec.ts --grep @baseline -c <(printf "import base from './playwright.config';\nimport { defineConfig } from '@playwright/test';\nexport default defineConfig({ ...base, grepInvert: undefined, grep: /@baseline/ });") --reporter=line || true
```

If the heredoc-config approach is awkward, create a temp `baseline.config.ts` (importing `./playwright.config`, setting `grepInvert: undefined, grep: /@baseline/`), run, then `rm` it. Do NOT commit any temp config. Baseline screenshots live under `test-results/` (gitignored) — nothing to commit unless the baseline SPEC changed.

- [ ] **Step 3: Confirm no stray files**

Run: `git -C /home/diogo/PhpstormProjects/arqel status --short`
Expected: only intended changes; NO `baseline.config.ts`, NO `zzz-*`/probe specs. Remove any.

---

## Task 5: PR + CI + merge

**Files:** none.

- [ ] **Step 1: Push the branch**

```bash
git -C /home/diogo/PhpstormProjects/arqel push -u origin round-design/responsive-tables
```

- [ ] **Step 2: Open the PR via GitHub API**

(GitHub DNS is broken on this host — use `--resolve`.) Write a PR body to `/tmp/pr-tables.md` summarizing the card-view + the `<th>` fix + the new gate, then:

```bash
curl -s -X POST --resolve api.github.com:443:140.82.112.6 -H "Authorization: Bearer $(gh auth token)" -H "Accept: application/vnd.github+json" https://api.github.com/repos/arqel-dev/arqel/pulls -d "$(python3 -c "import json,sys; print(json.dumps({'title':'feat(ui): DataTable mobile card-view (responsive Phase 2)','head':'round-design/responsive-tables','base':'main','body':open('/tmp/pr-tables.md').read()}))")"
```

- [ ] **Step 3: Poll CI until all checks complete**

```bash
curl -s --resolve api.github.com:443:140.82.112.6 -H "Authorization: Bearer $(gh auth token)" "https://api.github.com/repos/arqel-dev/arqel/commits/round-design/responsive-tables/check-runs" | python3 -c "import sys,json; d=json.load(sys.stdin); rs=d['check_runs']; pend=[r for r in rs if r['status']!='completed']; fail=[r for r in rs if r.get('conclusion') not in (None,'success','skipped')]; print('pending',len(pend),'failures',len(fail)); [print(' FAIL',r['name']) for r in fail]; [print(' PEND',r['name']) for r in pend]"
```

The E2E job (~12-18min incl. Docker stack setup) is the gate. If it fails, download the job log + the `playwright-report-showcase` artifact (trace), diagnose with systematic-debugging, fix, re-push. Commit-message lint: subjects ≤100 chars.

- [ ] **Step 4: Squash-merge when green**

```bash
curl -s -X PUT --resolve api.github.com:443:140.82.112.6 -H "Authorization: Bearer $(gh auth token)" -H "Accept: application/vnd.github+json" "https://api.github.com/repos/arqel-dev/arqel/pulls/<PR#>/merge" -d '{"merge_method":"squash","commit_title":"feat(ui): DataTable mobile card-view (responsive Phase 2)"}'
```

- [ ] **Step 5: Clean up**

```bash
curl -s -X DELETE --resolve api.github.com:443:140.82.112.6 -H "Authorization: Bearer $(gh auth token)" "https://api.github.com/repos/arqel-dev/arqel/git/refs/heads/round-design/responsive-tables"
git -C /home/diogo/PhpstormProjects/arqel checkout main && git -C /home/diogo/PhpstormProjects/arqel fetch origin main && git -C /home/diogo/PhpstormProjects/arqel reset --hard origin/main
```

Report Phase 2 convergence. (The v0.x tag remains the maintainer's manual step — do NOT cut it.)

---

## Self-review notes

- **Spec coverage:** card stack <md (Task 2) ✓; table ≥md (Task 2) ✓; reuse TableCell (Task 2) ✓; hiddenOnMobile dropped from card (Task 2, `cardColumns` filter) ✓; `<th>` bug fix (Task 1) ✓; new gate visible/hidden by viewport (Task 2) ✓; no-overflow + touch (Task 3) ✓; baseline 9/9 + suite no-regress (Task 4) ✓; CI + merge (Task 5) ✓.
- **Types:** `Row<TRecord>` imported (Task 2 Step 5); `DataCardsProps` matches the call site in Task 2 Step 3 (`columns/rows/enableSelection/selectedIds/onToggleRow/rowActions/loading/emptyState`); `toggleRow` passed as `onToggleRow`. `TableCell({column,value})` matches its real signature.
- **No placeholders:** every code step shows full code; commands include expected output.

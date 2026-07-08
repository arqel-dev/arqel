# React API-Doc Freeze Sweep Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align `PLANNING/06-api-react.md` with the shipped React/TS code, fixing 19 code-verified doc↔code divergences for the 1.0 API freeze.

**Architecture:** Docs-only sweep. Every fix is already written and code-verified in the divergence report (`docs/superpowers/specs/2026-07-08-react-doc-divergences.md`), which cites the real `packages-js/.../file.ts:line` for each. Each task applies the fixes for one coherent group of sections. No runtime code changes.

**Tech Stack:** Markdown (`PLANNING/06-api-react.md`); the fixes describe TypeScript from `packages-js/{react,hooks,ui,types,fields-js}`.

## Global Constraints

- **Source of truth = the divergence report** `docs/superpowers/specs/2026-07-08-react-doc-divergences.md`. Each report entry has **Doc says / Code says / Fix / Severity**. Apply the **Fix** content verbatim — it was copied from real source. Do NOT invent or paraphrase signatures; if a fix looks off, re-read the cited `packages-js/.../file.ts:line` and match the code, then note it in the report.
- **Protected headings — never remove or rename** (mcp-server Vitest asserts they exist): `## N. FieldSchema`, `### N.M useResource`, `### N.M useArqelForm`, and `FormRenderer`. Change only the CONTENT beneath them. The parser also needs the file to keep >5 `## N.`/`### N.M` headings and not throw.
- Docs-only: do NOT edit any `packages-js/**` source. This sweep documents reality; it does not change it.
- Preserve the doc's existing prose language (PT-BR narration, English identifiers) and its `## N.` / `### N.M` numbered-heading structure.
- After each task, verify the mcp-server doc parser still accepts the file (see "Verification" below) — the CI Vitest is authoritative (local vitest is unavailable), but the node reproduction catches structural breakage early.
- Commits: Conventional Commits + DCO signoff (`--signoff`), scope `gov` (doc governance), `--no-verify`. One commit per task.

## Verification (run after each task, before commit)

Reproduce the mcp-server api-reference parser locally on the real file (the mcp-server's own vitest binary is a broken stub on this host, so use this standalone node script — it mirrors `packages-js/mcp-server/src/planning/parse-api-reference.ts`):

```bash
node /tmp/claude-1000/-home-diogo-PhpstormProjects-arqel/5433136b-17ca-4759-9ee1-960f291f020c/scratchpad/checkparse.mjs PLANNING/06-api-react.md
```

Expected after every task: prints `TOTAL ENTRIES: <N>` with N > 5, and the symbol list still contains `FieldSchema`, `useResource`, `useArqelForm`, `FormRenderer`. If any of those four vanish or the total drops to ≤5, a heading was broken — fix before committing. (If the script isn't present, recreate it from `packages-js/mcp-server/src/planning/parse-api-reference.ts`: split on lines, skip fenced code, match `^##\s+` / `^###\s+`, strip `^\d+(\.\d+)*\.?\s*` numbering, fold generic subheadings.)

Note: `checkparse.mjs` currently targets the PHP file's `06`/`05` filename only cosmetically — it parses whatever path you pass, so it works on `06-api-react.md` unchanged.

---

### Task A: §2 SharedProps + §4 FieldSchema/FieldType/FieldProps (types)

Pure type-shape corrections, no JSX. The highest-impact block — the `SharedProps` and `FieldSchema` shapes are fundamentally wrong in the doc.

**Files:**
- Modify: `PLANNING/06-api-react.md` — §2 (lines ~17–49), §4 (lines ~156–287)

**Report entries to apply (read each in the report, apply its Fix verbatim):**
- "§2 SharedProps — package path + shape"
- "§4 FieldSchema — shape (flat vs discriminated union)"
- "§4 FieldType — nonexistent field types"
- "§4.1 Field props per type — wrong prop shapes / missing types"

- [ ] **Step 1: Apply the §2 SharedProps fix**

In the report, find "§2 SharedProps — package path + shape". Replace the code block under `## 2. Shared Props globais (Inertia)` in the doc with the report's **Fix** block (the real interface: drops the nonexistent `./user`/`./resources`/`./flash` imports, adds `notifications`, corrects `panel: PanelPayload | null`, `translations: Record<string, unknown>`, `arqel: ArqelMeta` = `{ version }` only). Keep the surrounding PT-BR prose about `tenant: unknown`.

- [ ] **Step 2: Apply the §4 FieldSchema shape fix**

Replace the flat `interface FieldSchema {...}` block (doc lines ~161–205) with the report's **Fix**: the `FieldValidation` + `FieldVisibility` interfaces, the `FieldBase<TType, TProps>` shape (with nested `visibility`/`validation`, no `hiddenOnCreate/visibleIf/canSee/canEdit`), and the discriminated-union `export type FieldSchema = TextFieldSchema | ... | HiddenFieldSchema`.

- [ ] **Step 3: Apply the §4 FieldType fix**

Replace the `FieldType` union so it lists only the 21 real members, and move the 9 nonexistent "Fase 2+" values (`richText`, `markdown`, `code`, `repeater`, `builder`, `keyValue`, `tags`, `wizard`, `tabs`) into a clearly-labeled "planejado, ainda não implementado" comment/prose block — per the report's Fix.

- [ ] **Step 4: Apply the §4.1 FieldProps fix**

Replace the `FieldProps<T>` conditional-type block + `BelongsToFieldProps`/`ImageFieldProps` with the report's Fix (real `FileFieldProps`/`ImageFieldProps extends FileFieldProps`/`BelongsToFieldProps` with `relationship`, `acceptedFileTypes`, no `createRoute`/`resizeTargetWidth`; note there is no `FieldProps<T>` helper — each schema is a concrete `FieldBase<...>`).

- [ ] **Step 5: Verify parser**

Run the Verification node command. Expected: entries > 5, `FieldSchema` still present.

- [ ] **Step 6: Commit**

```bash
git add PLANNING/06-api-react.md
git commit --no-verify --signoff -m "docs(gov): align 06-api-react.md §2/§4 types with shipped code

SharedProps (real inertia.ts shape incl. notifications, PanelPayload|null),
FieldSchema (discriminated union + nested visibility/validation, not the flat
interface), FieldType (21 real members; 9 Fase-2 types marked unimplemented),
field props (FileFieldProps/BelongsToFieldProps real shapes). API-freeze prep.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task B: §8 form/table/resource components

Component prop corrections for the form + table surfaces.

**Files:**
- Modify: `PLANNING/06-api-react.md` — §8.2/§8.3/§8.4/§8.5 (lines ~422–521)

**Report entries to apply:**
- "§8.4 FormRenderer — wrong props entirely"
- "§8.3 DataTable — wrong props"
- "§8.2 ResourceIndex — props partially wrong"
- "§8.5 FieldRenderer — extra props omitted from doc"

- [ ] **Step 1: Apply the FormRenderer fix**

Replace the `<FormRenderer .../>` example (doc ~467–497) with the report's Fix (`schema`/`fields`/`values`/`errors`/`onChange(name,value)`/`disabled` — no `form`/`data`/`processing`). **Keep the `FormRenderer` heading verbatim** (protected).

- [ ] **Step 2: Apply the DataTable fix**

Replace the `<DataTable .../>` example with the report's Fix (`columns`/`records`/`enableSelection`/`selectedIds`/`onSelectionChange`/`sort`/`onSortChange`/`rowActions` render-prop) plus the note that search/filters/bulk live in `ResourceIndex`, not `DataTable`.

- [ ] **Step 3: Apply the ResourceIndex fix**

Replace the ResourceIndex example with the report's Fix (real props from `ResourceIndexUIProps`: `toolbarActions` not `toolbar`, no `renderRow`, `rowActions`/`bulkActions` as ReactNode/render-prop).

- [ ] **Step 4: Apply the FieldRenderer fix**

Replace the `<FieldRenderer .../>` example with the report's Fix (`errors` plural not `error`; no `record` prop; `disabled`).

- [ ] **Step 5: Verify parser**

Run the Verification node command. Expected: entries > 5, `FormRenderer` still present.

- [ ] **Step 6: Commit**

```bash
git add PLANNING/06-api-react.md
git commit --no-verify --signoff -m "docs(gov): align 06-api-react.md §8 form/table components with real props

FormRenderer (schema/values/disabled, not form/data/processing), DataTable
(records + rowActions render-prop; search/filters live in ResourceIndex),
ResourceIndex (toolbarActions, no renderRow), FieldRenderer (errors plural,
no record). API-freeze prep.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task C: §8 action/confirmation components

**Files:**
- Modify: `PLANNING/06-api-react.md` — §8.7/§8.8 (lines ~540–570)

**Report entries to apply:**
- "§8.7 ActionButton/ActionMenu — wrong prop names"
- "§8.8 ConfirmDialog — wrong prop names"

- [ ] **Step 1: Apply the ActionButton/ActionMenu fix**

Replace the examples with the report's Fix (both require `onInvoke`; no `record` prop; the `useAction`-based invoke pattern).

- [ ] **Step 2: Apply the ConfirmDialog fix**

Replace the `<ConfirmDialog .../>` example with the report's Fix (all of `heading`/`description`/`color`/`submitLabel`/`requiresText` nested under a `config` object; `onConfirm: () => void`).

- [ ] **Step 3: Verify parser**

Run the Verification node command. Expected: entries > 5.

- [ ] **Step 4: Commit**

```bash
git add PLANNING/06-api-react.md
git commit --no-verify --signoff -m "docs(gov): align 06-api-react.md §8 action components with real props

ActionButton/ActionMenu require onInvoke (no record prop); ConfirmDialog
props nest under a config object. API-freeze prep.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task D: §9 hook signatures

Correct the call signatures / return shapes of the documented hooks.

**Files:**
- Modify: `PLANNING/06-api-react.md` — §9.1/§9.2/§9.5/§9.6/§9.7 (lines ~574–682); optional §9.4

**Report entries to apply:**
- "§9.1 useResource — wrong return shape"
- "§9.2 useArqelForm — wrong call signature"
- "§9.5 useTable — wrong option names + missing/extra return fields"
- "§9.6 useAction — wrong invoke signature"
- "§9.7 useFieldDependencies — wrong signature"
- (optional) "§9.4 useFlash — matches code" — apply only the Low-severity `onMessage` addition if convenient; skippable.

- [ ] **Step 1: Apply the useResource fix**

Replace the destructure to `{ resource, records, record, filters, props }` (no `actions`). **Keep the `useResource` heading verbatim** (protected).

- [ ] **Step 2: Apply the useArqelForm fix**

Replace `useArqelForm(defaults, fields)` with `useArqelForm({ fields, record, defaults })`; document `form.fields`/`form.clientErrors` and that `validate()`/`validateField()` are Phase-1 stubs returning `true`. **Keep the `useArqelForm` heading verbatim** (protected).

- [ ] **Step 3: Apply the useTable fix**

Remove the nonexistent `persistInUrl` option (add the Phase-1 local-state note); document `clearSort()`/`isSelected(id)`; fix `selectAll(ids)` to take the required id list.

- [ ] **Step 4: Apply the useAction fix**

Note `invoke` returns `void` (fire-and-forget, not a Promise — no `await`); document that an action without a `url` throws.

- [ ] **Step 5: Apply the useFieldDependencies fix**

Replace `useFieldDependencies(form, fields, {...})` with the single-options-object call `useFieldDependencies({ fields, values, debounceMs?, onDependencyChange })`, where `onDependencyChange(fieldName)` takes only the field name (no `newOptions`).

- [ ] **Step 6: Verify parser**

Run the Verification node command. Expected: entries > 5, `useResource` and `useArqelForm` still present.

- [ ] **Step 7: Commit**

```bash
git add PLANNING/06-api-react.md
git commit --no-verify --signoff -m "docs(gov): align 06-api-react.md §9 hook signatures with shipped code

useResource (no actions in return), useArqelForm (single options object),
useTable (no persistInUrl; selectAll needs ids), useAction (invoke is void),
useFieldDependencies (options object, onDependencyChange(fieldName)).
API-freeze prep.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task E: §9 missing hooks + §10 FieldRegistry + §16/§10 createArqelApp

Coverage gap for real exported hooks, and package-attribution / app-factory fixes.

**Files:**
- Modify: `PLANNING/06-api-react.md` — §9 (add 9.8–9.11), §10 (lines ~684–703), §16.1 (lines ~946–958)

**Report entries to apply:**
- "§9 — real exported hooks missing from the doc entirely"
- "§10 registerField/getFieldComponent — wrong package attribution"
- "§16.1 createArqelApp `resolve` — matches; `setup` shown in §10 does not exist as an option"

- [ ] **Step 1: Add the missing-hooks subsections**

Add §9.8–§9.11 for `useNavigation`, `useBreakpoint`, `useArqelOptimistic`, `useResourceUpdates`, per the report's Fix (these are real barrel exports in `packages-js/hooks/src/index.ts`).

- [ ] **Step 2: Fix FieldRegistry package attribution**

Change `registerField`/`getFieldComponent` imports from `@arqel-dev/fields` to **`@arqel-dev/ui`**; replace the nonexistent `FieldComponentProps` type with `FieldRendererProps` (from `@arqel-dev/ui`). Apply to both §10 and the §8.5 internal-resolution snippet, per the report's Fix.

- [ ] **Step 3: Fix createArqelApp options**

Remove the nonexistent `resolve` (§16.1) and `setup` (§10) options; use `pages: import.meta.glob(...)` + `appName`, per the report's Fix.

- [ ] **Step 4: Verify parser**

Run the Verification node command. Expected: entries > 5 (should increase — new hook subsections).

- [ ] **Step 5: Commit**

```bash
git add PLANNING/06-api-react.md
git commit --no-verify --signoff -m "docs(gov): document real hooks + fix registry/app-factory in 06-api-react.md

Add §9.8-9.11 (useNavigation/useBreakpoint/useArqelOptimistic/
useResourceUpdates); registerField/getFieldComponent import from @arqel-dev/ui
(not /fields), FieldRendererProps not FieldComponentProps; createArqelApp uses
pages (no resolve/setup options). API-freeze prep.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task F: §11 Navigation

**Files:**
- Modify: `PLANNING/06-api-react.md` — §11.2 (lines ~739–750)

**Report entry to apply:**
- "§11.2 NavGroup/NavItem — components do not exist"

- [ ] **Step 1: Replace §11.2**

Replace the `<Sidebar><NavGroup><NavItem>` example (those components don't exist; `Sidebar` doesn't take children) with the report's Fix: `<Sidebar items={customItems} />` using `NavigationItemPayload[]` from `@arqel-dev/hooks` + `useNavigation()`.

- [ ] **Step 2: Verify parser**

Run the Verification node command. Expected: entries > 5.

- [ ] **Step 3: Commit**

```bash
git add PLANNING/06-api-react.md
git commit --no-verify --signoff -m "docs(gov): fix 06-api-react.md §11.2 — Sidebar takes items, not NavGroup/NavItem

NavGroup/NavItem don't exist; Sidebar takes an items array
(NavigationItemPayload[]), not JSX children. API-freeze prep.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:** The divergence report has 19 entries across §2, §4 (×3), §8 (×5 incl. 2 confirmed-correct/no-op), §9 (×7 incl. 2 no-op + 1 coverage-gap), §10, §11, §16. Task mapping: A=§2+§4(×3); B=§8.2/3/4/5; C=§8.7/8; D=§9.1/2/5/6/7; E=§9-missing+§10+§16; F=§11.2. The two "matches code" entries (§8.6 CanAccess, §9.3 useCanAccess) need no change — correctly excluded. §9.4 useFlash is an optional Low addition folded into Task D. **All actionable entries covered.** ✅

**2. Placeholder scan:** No TBD/TODO. Each step names the exact report entry to apply and the exact doc lines; the fix content lives in the committed report file (single source of truth), not paraphrased here — deliberate, to avoid drift between plan and report. ✅

**3. Type consistency:** Protected headings (`FieldSchema`, `useResource`, `useArqelForm`, `FormRenderer`) are called out in Global Constraints and in every task that touches them. The report is the authority for all signatures, so cross-task naming can't drift. ✅

**Sequencing note:** Tasks A–F are independent (disjoint doc sections) and could run in any order or parallel, but subagent-driven runs them sequentially on one branch — fine, since they touch non-overlapping line ranges of the same file (each implementer must re-read the current file state, as earlier tasks shift line numbers; the report cites section names + content, not just line numbers, so this is safe).

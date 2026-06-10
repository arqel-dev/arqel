# Round 23 — Detection findings (showcase-covered surfaces, adversarial)

> After Round 22 fixed the 8 bugs surfaced *during construction* of the showcase expansion, Round 23 is the loop's other half: an **adversarial detection sweep** over the newly-covered surfaces to find what the happy-path E2E missed. 3 detection agents read `main` (HEAD ~2d267ca), traced adversarial scenarios, refuted-first, and confirmed **13 NEW defects** (all distinct from #229-#237). Verified by reading framework source; several confirmed against the live dogfood stack. This ledger feeds the fix pipeline (issue → TDD → PR → merge), exactly like Rounds 1-22.

## Severity-ordered summary

| # | Sev | Surface | Title |
|---|---|---|---|
| R23-1 | **HIGH (security)** | realtime | Presence channel fails OPEN vs Policy-protected records → roster leak cross-user/tenant |
| R23-2 | **HIGH (security/integrity)** | actions | `->disabled()` action still dispatchable via the new endpoint (no server-side disabled check) |
| R23-3 | **MED-HIGH (security)** | form | Generated FormRequest uses `fields()` not `effectiveFields()` → wrong validation set (mass-assignment) |
| R23-4 | **MED-HIGH** | workflow | spatie path of `transitionTo()` skips `TransitionAuthorizer` (documented authz not enforced) |
| R23-5 | **HIGH (functional)** | table/ui | `TrashedFilter` doesn't render in the UI (PHP `type:'trashed'` ↔ React switch has no case) |
| R23-6 | **HIGH (functional)** | core | Restoring a soft-deleted record is impossible via any resource route (`findOrFail` excludes trashed) |
| R23-7 | **HIGH (functional)** | core/actions | Stock `restore` action serializes `POST {slug}/{id}/restore` but no such route is registered → 404 |
| R23-8 | **HIGH (functional)** | fields | Stock `ImageInput`/`FileInput` never persist the file (write-path has no storage step) → upload broken |
| R23-9 | **MED** | actions | Toolbar action invocable with an arbitrary `{id}`; ability chosen by id-presence, not action type |
| R23-10 | **MED** | theme/a11y | `resolved` theme initial state hardcoded `'light'` → inverted toggle icon/aria-label on first paint |
| R23-11 | **MED** | a11y | `useFocusTrap` Tab-forward asymmetry lets focus escape when it starts outside the container |
| R23-12 | **LOW-MED** | actions | Every custom action implicitly gated by `update`/`viewAny`; no per-action ability (deny-OFF default) |
| R23-13 | **LOW** | i18n / theme / workflow | locale allowlist-vs-disk mismatch; orphan theme storage helpers; spatie history raw-`to` |

---

## R23-1 — [SECURITY, HIGH] Presence channel fails OPEN against Policy-protected records → roster leak

**Surface:** realtime. **File:** `packages/realtime/routes/channels.php:67-86` (presence channel callback).

**The gap:** the presence channel's only authorization is `Gate::has('view-resource-presence') && ! Gate::forUser($user)->allows('view-resource-presence', ...)`. If the app does NOT define the named gate `view-resource-presence` (the default — the config only toggles `presence.enabled`/`channel_pattern`, never defines the gate), `Gate::has(...)` is `false`, the guard short-circuits, and **every authenticated user is authorized** to join the presence channel of ANY resource/record — receiving the `{id, name, avatar}` roster of all other present users.

**Smoking gun:** the sibling `AwarenessChannelAuthorizer` (collab), registered just above, was DELIBERATELY hardened against exactly this (`AwarenessChannelAuthorizer.php:55`): it checks `Gate::has('view') || Gate::getPolicyFor($record) !== null` and only opens on pure scaffold (no gate AND no policy). Its comment (lines 49-54) says without `getPolicyFor()` "a Policy-protected record would fall into allow-all and leak the collab channel." The presence channel never got the same hardening — it never consults the record's `view` Policy. In standard Arqel/Laravel apps (authz via Policy, not a named gate), the presence roster of protected records leaks cross-user/cross-tenant.

**Tests masked it:** `tests/Feature/PresenceChannelTest.php` only covers gate-define-deny + gate-define-allow (it `Gate::define`s the gate in both cases), masking the gate-undefined fail-open path. No test for the no-gate default.

**Fix direction:** mirror `AwarenessChannelAuthorizer` — resolve the record via slug/registry and, absent the `view-resource-presence` gate, fall back to the record's `view` Policy (`Gate::getPolicyFor($record)`), opening only on real scaffold (no gate AND no policy).

## R23-2 — [SECURITY/INTEGRITY, HIGH] `->disabled()` action still dispatchable via the new endpoint

**Surface:** actions. **File:** `packages/core/src/Http/Controllers/ResourceController.php:254-328` (`rowAction` never consults `isDisabledFor`); cf. `InertiaDataBuilder.php:756-758` (render path sends `disabled:true` only as a UI flag) + `Action::resolveStockUrl` (`actions/src/Action.php:458-463`, emits the dispatch url even for a disabled action).

**The gap:** the dispatch endpoint authorizes via the resource Gate (`update`/`viewAny`) + the action's `canBeExecutedBy`, but NEVER evaluates the per-record `->disabled()`/`->hidden()`/`->visible()` predicates server-side. So a `->disabled(fn($r)=>$r->status==='published')` action — which the UI greys out — is still dispatchable by POSTing `/admin/posts/actions/publish/{id}` directly. The render path respects `disabled`; the write path doesn't (render-vs-write asymmetry, the family seen in #102/#127, now on the new endpoint).

**Repro:** `PostResource.php:179-185` — `publish` row action `->disabled(fn($r)=>$r?->status==='published')`. A published Post: UI button disabled, but a direct POST re-runs `execute()` (Gate `update` + `canBeExecutedBy` both true).

**Fix direction:** in `rowAction`, after resolving the action + record, call `isDisabledFor($record)` (and `isVisibleFor`/hidden) and `abort(403)` when disabled/invisible — mirroring the `InertiaDataBuilder` filter. **Distinct from #232** (that was a null-record template serialization crash; this is the write-path bypass).

## R23-3 — [SECURITY, MED-HIGH] Generated FormRequest validates `fields()`, not `effectiveFields()`

**Surface:** form. **File:** `packages/form/stubs/form-request.stub` (`rules()`/`messages()`/`attributes()` call `$resource->fields()`).

**The gap:** the runtime `ResourceController` validates via `effectiveFields($record)` (`ResourceController.php:822`) — layout-aware + visibility-pruned (derived from `form()->getFields($record)`). The GENERATED FormRequest instead calls `fields()` — the flat, record-agnostic list that does NOT descend the `form()` layout. When a Resource declares its schema via `form()` (the documented pattern that `effectiveFields` exists to support so you needn't re-declare every field in `fields()`), the two rule sets DIVERGE.

**Concrete evidence (showcase):** `PostResource::fields()` has `author_id` NOT `->required()` (line 96); `PostResource::form()` has `author_id` `->required()` (lines 129-131). The generated FormRequest (via `fields()`) doesn't require `author_id`, while the controller (via `effectiveFields`→`form()`) does. Apps using `--with-requests` get rules inconsistent with the canonical path — fields hardened only in `form()` can pass unvalidated (mass-assignment of an unvalidated value). The stub also has no `$record`, so record-dependent `visibleIf` never applies.

**Fix direction:** the stub should call `$resource->effectiveFields()` (not `fields()`) in all three methods, aligning with the `effectiveFields` contract (PR #44). **Distinct:** the runtime controller path is correct (verified — pruneUnauthorizedFields #115, visibleIf threading #198); only the generated stub is wrong.

## R23-4 — [SECURITY, MED-HIGH] spatie path of `transitionTo()` skips the `TransitionAuthorizer`

**Surface:** workflow. **File:** `packages/workflow/src/Concerns/HasWorkflow.php:127-139`.

**The gap:** `assertTransitionAllowed()` (graph check + `TransitionAuthorizer`) is only called in the FALLBACK path (line 135). When `state` is an object with `transitionTo()` (the spatie/laravel-model-states path, lines 127-129), the transition is delegated straight to spatie and NO `TransitionAuthorizer` check runs (no `authorizeFor`/Gate `transition-*-to-*`/deny-by-default). The trait docblock (lines 28-33) promises first-class spatie compatibility, so a model with `$casts=['state'=>PendingState::class]` calling `$order->transitionTo(PaidState::class)` bypasses the server-side authz the package advertises (the very enforcement `UnauthorizedTransitionException` was created for).

**Why E2E missed it:** the dogfood app (`Order`/`Ticket`) uses `state` as a raw string (fallback path), never the spatie path — exactly the un-probed branch.

**Fix direction:** run `assertTransitionAllowed($definition, $fromKey, $newState)` BEFORE the branch, for both paths (or at least call `TransitionAuthorizer::authorize` on the spatie branch before delegating).

## R23-5 — [FUNCTIONAL, HIGH] `TrashedFilter` doesn't render in the UI (PHP↔React contract mismatch)

**Surface:** table/ui. **Files:** `packages/table/src/Filters/TrashedFilter.php:36` (`$type = 'trashed'`) ↔ `packages-js/ui/src/table/TableFilters.tsx:72-85` (the `FilterControl` switch: `select|multiSelect|text|ternary|dateRange|scope` — NO `trashed` case, NO `default`) ↔ `packages-js/types/src/tables.ts:168-174` (`FilterSchema` union has no `'trashed'` variant).

**The gap:** PHP serializes `type:'trashed'` with `{options:[...]}`. The React `FilterControl` switches on `filter.type` and, with no matching case and no default, returns `undefined` → renders nothing. So the #235 primitive is invisible/unusable in the UI.

**Field evidence:** `apps/showcase/.../OrderResource.php:111-129` STILL hand-rolls a `SelectFilter::make('trashed')` with `apply()` closures (type `'select'`, which renders) — proving the new `TrashedFilter` primitive isn't consumable.

**Fix direction:** either change `TrashedFilter::$type` to `'select'` (its props already have select shape → renders immediately), or add a `case 'trashed'` + `TrashedFilterSchema` to the union + a React component. The first is trivial + correct. **Distinct from #235** (#235 shipped only the PHP primitive; this is the React/serialization half).

## R23-6 — [FUNCTIONAL, HIGH] Restoring a soft-deleted record is impossible via any resource route

**Surface:** core. **File:** `packages/core/src/Http/Controllers/ResourceController.php:558-569` (`findOrFail` does `$modelClass::query()->find($id)`), used by `rowAction` (line 264), `show`/`edit`/`update`/`destroy`.

**The gap:** for a SoftDeletes model the global scope hides trashed rows, so `find($id)` of a deleted record → null → `abort(404)`. The `restore` action exists (`Actions.php:47-51`); if exposed as a custom action it dispatches via `rowAction` → `findOrFail` → 404. You can never load the trashed record to restore it.

**Fix direction:** `findOrFail` needs an "include trashed" mode (e.g. when the model is SoftDeletes and the target action is restore/forceDelete, or an opt-in param) using `withTrashed()->find($id)`. **Distinct from #235** (#235 explicitly deferred the restore/forceDelete actions; this is the controller gap that blocks them even if an app adds them).

## R23-7 — [FUNCTIONAL, HIGH] Stock `restore` action serializes a URL to an unregistered route → 404

**Surface:** core/actions. **Files:** `packages/actions/src/Action.php:431` (`['row','restore'] => ["/admin/{slug}/{id}/restore",'POST']`) ↔ `packages/core/routes/arqel.php` (no `restore` route).

**The gap:** `Actions::restore()` has no callback → `resolveStockUrl` emits `POST /admin/{slug}/{id}/restore`. But `routes/arqel.php` registers only index/create/store/show/edit/update/destroy/bulk/actions — there is NO `{resource}/{id}/restore` route. The frontend POSTs to a non-existent route → 404. Combined with R23-6, stock `restore` is doubly broken.

**Fix direction:** register `POST {resource}/{id}/restore` → a `ResourceController::restore` handler that uses `withTrashed()` + `$record->restore()` + a `restore`/`update` gate.

## R23-8 — [FUNCTIONAL, HIGH] Stock `ImageInput`/`FileInput` never persist the uploaded file

**Surface:** fields. **Files:** `packages-js/fields-js/src/file/FileInput.tsx:6-8,34` + `ImageInput.tsx:69` (hold the raw `File`, submit multipart via the main form) ↔ `packages/core/src/Resources/Resource.php:259-294` (`runCreate`/`runUpdate` do only `fill($data)->save()`, NO storage step) ↔ `packages/fields/src/Http/Controllers/FieldUploadController.php` (a direct-upload endpoint EXISTS but no stock component calls it).

**The gap:** `ImageField('file_path')->disk('public')->directory('media')` → `ImageInput` `onChange(file)` with the `File` object → Inertia multipart → arrives as `UploadedFile` → `uploadRule()` lets it pass validation → `fill(['file_path'=><UploadedFile>])` casts the `UploadedFile` (a `SplFileInfo`) to string → stores the **temp path** (`/tmp/phpXXXX`) in the column, which vanishes at request end. The real file is **never moved** to `storage/.../media`. The `ImageColumn` then points at a non-existent path.

**Why real:** the well-built `FieldUploadController` (hashName, sanitization, content-based mimetypes) is ORPHANED — the `FileInput` docstring claims "the actual upload happens server-side via Inertia's multipart" but the write-path has no storage. `MediaResource` uses the stock pattern with no `beforeSave` hook → expected use, not app-misuse. `MediaAssetTest` only tests the factory, never the HTTP upload (tests-mask-integration-gaps).

**Fix direction:** either (a) the write pipeline detects an `UploadedFile` per file/image field and `store()`s it to the configured disk/directory before `fill()`, or (b) the `FileInput`/`ImageInput` POST to `FieldUploadController` first and submit the returned path. (a) is more coherent with `MediaResource`.

## R23-9 — [MED] Toolbar action invocable with an arbitrary `{id}`; ability chosen by id-presence not action type

**Surface:** actions. **File:** `ResourceController.php:272-277` (ability = `$record !== null ? 'update' : 'viewAny'`, never the action's `getType()`); `findResourceAction` (`:339-363`) merges row/header/toolbar/table actions without discriminating type; route is `{id?}` (`routes/arqel.php:95`).

**The gap:** a TOOLBAR action (no record, should gate on `viewAny`) can be called as `POST /admin/{slug}/actions/{toolbarName}/{id}`; `rowAction` then loads the record, gates on `update`, and injects the record into a closure written expecting `record=null` → undefined behavior / possible mutation. Conversely a ROW action called WITHOUT an id runs with `record=null` under the weaker `viewAny` gate, and its closure (`fn($r)=>$r->update(...)`) gets null → 500 or silent no-op. The client always sends the "right" shape, so E2E never exercises the cross form.

**Fix direction:** validate `$actionInstance->getType()` against id-presence (toolbar ⇒ reject id; row/header ⇒ require id) and derive the ability from the type, not id-presence.

## R23-10 — [MED, a11y] Theme `resolved` initial state hardcoded `'light'` → inverted toggle on first paint

**Surface:** theme/a11y. **File:** `packages-js/react/src/providers/ThemeProvider.tsx:63` (`useState<ResolvedTheme>('light')`), corrected only in the post-mount `useEffect` (lines 76-107); consumer `Topbar.tsx:33,51,54`.

**The gap:** `preventFlashScript` applies `dark` to `<html>` before hydration, but the first React render has `resolved==='light'`. The Topbar renders the `☾` icon + `aria-label="Switch to dark theme"` even when the page is visibly dark — the toggle's icon/label are WRONG until the effect runs. Screen readers announce the inverted toggle target on first paint. Adjacent to #236 (the unification kept the hardcoded init).

**Fix direction:** initialize `resolved` via a lazy initializer reading `document.documentElement` (the class/`data-theme` the script already applied) or `localStorage` + `matchMedia`, instead of a fixed `'light'`.

## R23-11 — [MED, a11y] `useFocusTrap` Tab-forward asymmetry lets focus escape from outside the container

**Surface:** a11y. **File:** `packages-js/a11y/src/useFocusTrap.ts:80-90`.

**The gap:** the `shiftKey` branch (line 81) redirects when `activeEl===first || !container.contains(activeEl)`. The forward branch (lines 85-89) only redirects when `activeEl===last` — it does NOT handle `!container.contains(activeEl)`. If focus escapes the container and the user presses Tab forward, focus is NOT pulled back. Violates the "Tab never leaves the container" invariant.

**Fix direction:** mirror the shift condition in the forward branch: `if (activeEl===last || !container.contains(activeEl)) { preventDefault(); first.focus(); }`.

## R23-12 — [LOW-MED] Every custom action implicitly gated by `update`/`viewAny`; no per-action ability

**Surface:** actions. **File:** `ResourceController.php:272-277` (hardcoded ability); the action's only own authz is `canBeExecutedBy`, which defaults to `true` (`HasAuthorization.php:27-34`) when no `->authorize()` closure was declared.

**The gap:** a `refund`/`approve`/`ban-user` row action needs only the resource's `update` ability + (optionally) an `authorize()` closure the dev must remember to write. There's no `Action::authorize('refund')` mapping to a Policy ability. Whoever can edit the row can fire any custom row action without an explicit `->authorize()`. This deny-OFF-by-default is the OPPOSITE of the workflow `TransitionAuthorizer` (deny-by-default) — an internal inconsistency between the two authz subsystems.

**Fix direction:** allow `Action::authorize('refund')` (string ⇒ Gate ability) alongside the closure; at minimum align the default posture with the workflow's deny-by-default + document that the resource gate is `update`.

## R23-13 — [LOW] Three minor footguns

- **i18n locale allowlist-vs-disk mismatch** (`TranslationLoader.php:111-124` + `LocaleController.php:36-46`): the allowlist comes from config (`pt_BR`) but `loadForLocale` does `is_dir($base.'/'.$locale)`. An app configuring `pt-BR` (hyphen — which `LocaleSwitcher`'s `DEFAULT_LABELS` includes) passes the `in_array` switch but `loadForLocale('pt-BR')` misses the `pt_BR` dir → silently falls to default. Fix: normalize `-`→`_` or validate the allowlist against on-disk dirs at boot.
- **Orphan theme storage helpers** (`packages-js/theme/src/storage.ts:28`): `writeStoredTheme`/`readStoredTheme` are still exported but the unified provider only reads localStorage once at mount and inline — calling `writeStoredTheme('dark')` at runtime writes the key but doesn't update the provider state. Documented helper with no integrated effect.
- **spatie history raw-`to`** (`HasWorkflow.php:129,151-157`): on the spatie path the `StateTransitioned` event fires with the raw `to:$newState` argument, which the listener persists to `to_state`. If spatie normalizes the persisted value (FQCN vs slug), the history diverges from the model's real column. Fix: re-read `$this->{$field}` + `resolveStateKey()` after the transition.

---

## Systemic themes (Round 23)

1. **Render-path vs write-path asymmetry** (R23-2): the server filters/respects a flag in the client serialization but the dispatch endpoint doesn't re-validate it — the same family as #102/#127, now on the brand-new action endpoint.
2. **Documented-authz-not-enforced on one of two branches** (R23-4): exactly why `UnauthorizedTransitionException` was created — the spatie branch was never wired to it.
3. **A new primitive shipped half-way** (R23-5, R23-6/7, R23-8): TrashedFilter (PHP-only, no React), restore (action+url, no route+no trashed-load), uploads (component+controller, not connected) — each shipped one side of a two-sided contract.
4. **tests-mask-integration-gaps** (R23-1 presence, R23-8 upload): the tests `Gate::define` the happy path / test the factory, masking the fail-open / no-storage path.
5. **Security fail-open vs Policy** (R23-1): one channel authorizer hardened, its sibling not — the kind of inconsistency a per-surface sweep catches.

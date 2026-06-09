# Showcase Expansion — Round-22 Candidate Findings

> **Fluxo (confirmado com o utilizador, 2026-06-08):** estes são candidatos a bug de framework que surgiram ENQUANTO se constrói a expansão da showcase (Fases 1-6), antes do workflow formal de detecção. Cada um é verificado com evidência e um workaround app-side aplicado para a construção prosseguir. **NÃO são ainda issues GitHub.** No **Round 22 (Fase 7)** — tal como nos Rounds 1-21 do loop original — cada candidato passa pelo pipeline completo: verificação adversarial (refutar primeiro → classificar framework-bug vs app-misuse vs not-a-bug) → criar issue GitHub para os confirmados → corrigir via TDD (falha-antes/passa-depois) → PR → merge em CI CLEAN. Este ficheiro é o registo interno que alimenta esse Round 22; o destino final de cada framework-bug confirmado É um issue GitHub + fix PR.

## CANDIDATE #1 — enforceMorphMap + LogsActivity causer → ClassMorphViolationException (500 on every write)

**Surfaced:** Task 2.2 (Attachment morph + app-level enforceMorphMap).
**Severity (suspected):** HIGH — any app following the documented best practice breaks on every authenticated write.

**The gap:** `Relation::enforceMorphMap()` runs in strict mode (`merge=false`), so any model used polymorphically but absent from the map throws `Illuminate\Database\ClassMorphViolationException`. The framework's `LogsActivity` concern (spatie/laravel-activitylog) associates the authenticated `User` as the activity **causer**, which is a `MorphTo` relation (`packages/audit/vendor/spatie/laravel-activitylog/src/Models/Activity.php:78`). So the auth model participates polymorphically — exactly like the subject.

**Why it's untested at framework level:** the morph-alias work (#72/#190) and `packages/audit/tests/Feature/RecordActivityMorphMapTest.php` cover the activity **subject** only — that test constructs `Activity` rows manually (`$activity->subject_type = $model->getMorphClass()`, line 59) and never authenticates a causer that gets persisted through the strict morph map. The **causer-under-strict-morph-map** path is uncovered.

**Reproduction (showcase):** with `enforceMorphMap(['post'=>Post::class, ...])` active (NOT mapping User) + a model using `LogsActivity` + an authenticated user, `ShowcaseSmokeTest::test_post_store_validates_then_creates` and `OrderWorkflowTest` both threw `ClassMorphViolationException: No morph map defined for model [App\Models\User]` on write. App-level workaround: add `'user' => User::class` to the map (done in Task 2.2).

**Suggested framework fix:** when audit (`LogsActivity`) is active and a morph map is enforced, either (a) auto-register the configured auth provider model(s) in the morph map, or (b) document prominently that enabling `enforceMorphMap` requires mapping the auth User model. Add a test that authenticates a causer under an enforced morph map and asserts the write succeeds + the causer_type stores the alias.

**Distinct from:** #72 (versioning prune morphclass), #190 (audit RecordActivityController subject_type alias) — those handle the *subject*; this is the *causer*.

## CANDIDATE #2 — Action disabled()/visible() closures invoked with null record at template serialization → 500

**Surfaced:** Task 3.1 (custom actions on PostResource).
**Severity (suspected):** MEDIUM — a documented per-record predicate (`->disabled(fn($r)=>$r->status===...)`) 500s the resource index page unless the app author makes every closure null-safe, with nothing in the API signalling this.

**The gap:** `Action::isDisabledFor(mixed $record = null)` (`packages/actions/src/Action.php:266`) and `isVisibleFor` invoke `($this->disabled)($record)` / `($this->visible)($record)` with NO null guard on `$record` — they only guard whether the closure itself is null. But `InertiaDataBuilder::callToArray` (`packages/core/src/Support/InertiaDataBuilder.php:534`) deliberately serializes the row-action TEMPLATE via `$item->toArray($user, null, $resource)` with a **null record**, and `Action::toArray()` (`Action.php:339`) calls `isDisabledFor($record)` with that null. So any per-record `disabled`/`visible` predicate is invoked with `$record === null` at serialization time → `Attempt to read property "status" on null` → 500 on the index page.

**Reproduction (showcase):** `RowAction::make('publish')->disabled(fn ($record) => $record->status === 'published')` on PostResource → 500 on `/admin/posts`. App-side workaround: `$record?->status === 'published'` (null-safe), done in Task 3.1.

**Distinct from #140 (Round 8):** #140 fixed `resolveUrl` (the row-action URL closure) to tolerate a null record. The `isDisabledFor`/`isVisibleFor` predicates are a SEPARATE entry point through the same null-record-template serialization path — they were NOT guarded by #140. Same family ("per-record action predicate invoked with null at template serialization"), distinct entry point — the recurring next-layer pattern.

**Suggested framework fix:** in `isDisabledFor`/`isVisibleFor`, return the static default (false / true) when `$record === null` (short-circuit before invoking the closure), mirroring how `resolveUrl` was hardened in #140. Or document the null-record template contract on the closure-accepting builders.

## CANDIDATE #3 — workflow ships StateTransitionField + transitionTo() but NO HTTP transition endpoint

**Surfaced:** Task 3.3 (TicketResource workflow wiring).
**Severity (suspected):** MEDIUM — but likely a DEFERRED FEATURE, not a bug (see classification note).

**The gap:** `arqel-dev/workflow` provides the UI field (`StateTransitionField`, renders transition buttons + the `transitions`/`authorized`/`history` payload) and the model method (`HasWorkflow::transitionTo()`), but there is NO HTTP endpoint to fire a transition from a button click. Every consuming app must hand-roll its own route + controller (the showcase added `POST /admin/tickets/{ticket}/transition` → `TicketTransitionController` for this reason).

**Evidence:** `find packages/workflow/src -name "*.php" | xargs grep -l "Route::"` → ZERO matches. `transitionTo` appears only in model concerns/events/exceptions, never in an HTTP controller. `packages/workflow/src/WorkflowServiceProvider.php:21` explicitly comments: "`TransitionController` and React visualizer land in WF-003+."

**Sub-point (wiring ergonomics):** `StateTransitionField` inside a Resource `form()` gets NO automatic record-binding from core's `InertiaDataBuilder`, so `currentState`/`transitions`/`history` always serialize empty for the edited record unless the Resource explicitly calls `->record($model)`. The field can't show live transition buttons for the current record out of the box. Not a crash — a wiring ergonomics gap.

**⚠️ CLASSIFICATION NOTE for Round 22:** the framework DOCUMENTS this as WF-003+ (planned future work), exactly like EXPORT-007/008 / CORE-006 deferred items that the loop classified as NOT-A-BUG (deferred feature, not a defect). The Round 22 adversarial verifier should likely classify this as a **deferred-feature non-bug** UNLESS the docs/SKILL.md claim the transition endpoint already works (a documented-but-false capability would make it a real bug). Action for Round 22: check whether workflow's SKILL.md / docs advertise a working transition endpoint; if they do, it's a bug; if they honestly mark it WF-003+, it's deferred.

## CANDIDATE #4 — inconsistent Field::make() factory (DX papercut, LOW)

**Surfaced:** Task 3.4 (MediaResource ImageField).
**Severity (suspected):** LOW — DX footgun, not a crash of framework code (the crash is the app using a non-existent method). Likely a papercut / minor-enhancement, not a bug.

**The inconsistency:** `Column::make($name)` (`packages/table/src/Column.php:80`) and `Action::make($name)` (`packages/actions/src/Action.php:97`) exist on the base classes, so EVERY column/action is constructed via `::make()`. But the base `Field` (`packages/fields/src/Field.php`) has NO static `make()` — fields are constructed with `new TextField(...)`. EXCEPT a handful of field types define their own `make()`: `HasManyField`, `BelongsToField` (`packages/fields/src/Types/`), and `StateTransitionField` (`packages/workflow/src/Fields/`). So the API is inconsistent even within the fields family: `BelongsToField::make()` works, `ImageField::make()` / `TextField::make()` do NOT.

**Reproduction:** `ImageField::make('file_path')` → `Call to undefined method Arqel\Fields\Types\ImageField::make()` — 500 on the form (edit/create) page; the INDEX page masks it (it doesn't build the form). App-side fix: `new ImageField('file_path')`.

**Why it's a footgun:** a developer who learned `Column::make()`/`Action::make()`/`BelongsToField::make()` reasonably expects `ImageField::make()` to work; it silently doesn't, and the error only surfaces on form-building routes, not the index.

**Suggested framework fix:** add a static `make(string $name): static` to the base `Field` (mirroring Column/Action), so ALL fields share the consistent factory. Or, if `new` is the intended idiom for fields, REMOVE the per-type `make()` from HasManyField/BelongsToField/StateTransitionField for consistency + document the `new`-for-fields convention. Either way, make it consistent.

**Classification note for Round 22:** this is a DX/consistency papercut, likely LOW or a not-a-bug-but-enhancement. The app-side error was a usage mistake (calling a method that doesn't exist), so a strict verifier may classify it app-misuse — but the INCONSISTENCY (some fields have make(), most don't) is a genuine framework-side wart worth surfacing.

## CANDIDATE #5 — two parallel, incompatible theme systems (`@arqel-dev/react/providers` vs `@arqel-dev/theme`); the public `@arqel-dev/theme` toggle is a no-op next to the shell's built-in one

**Surfaced:** Task 4.2 (wiring theme/i18n/a11y/realtime providers into the showcase layout).
**Severity (suspected):** MEDIUM — not a crash, but a real architectural split that makes the published `@arqel-dev/theme` package effectively dead weight inside any app that uses `@arqel-dev/ui/shell`, and silently produces two competing theme states.

**The gap:** there are TWO independent ThemeProvider/useTheme implementations, each with its own React context object:
- `@arqel-dev/react/providers` — `ThemeProvider` + `useTheme` (`packages-js/react/src/providers/ThemeProvider.tsx`). `createArqelApp` wraps the WHOLE app in `ArqelProvider` → this `ThemeProvider` (`packages-js/react/src/inertia/createArqelApp.tsx:105`). The shell `Topbar` (`packages-js/ui/src/shell/Topbar.tsx:10,33`) renders its built-in theme toggle by calling `useTheme()` from THIS package.
- `@arqel-dev/theme` — a SEPARATE `ThemeProvider`/`useTheme`/`ThemeToggle` surface (`packages-js/theme/src/`), with its OWN `ThemeContext` (`packages-js/theme/src/ThemeProvider.tsx:6`) and richer `ThemeProvider` props (`storageKey`, `darkClass`, `attribute`). It also exports a standalone `preventFlashScript(options?) => string` helper (`packages-js/theme/src/preventFlash.ts`) — a function, NOT a `ThemeProvider` prop. This is the package the showcase task wires in and that any app would `import { ThemeProvider, ThemeToggle } from '@arqel-dev/theme'`.

Because the contexts are different objects, `@arqel-dev/theme`'s `ThemeToggle`/`useTheme` read/write a context that the shell `Topbar` neither reads nor writes (and vice-versa). Wrapping the layout in `@arqel-dev/theme`'s `ThemeProvider` does NOTHING for the Topbar's built-in toggle, which is already satisfied by the `react/providers` context that `createArqelApp` injects.

**Reproduction:** In the showcase, the Topbar renders fine with no `@arqel-dev/theme` ThemeProvider in the tree (the `react/providers` one from `createArqelApp` is what backs it). If you instead place a `@arqel-dev/theme` `<ThemeToggle />` in the layout, clicking it toggles a *second*, independent theme state that the Topbar's ☾/☀ button ignores — two toggles, two truths, and the `react/providers` one (no `storageKey`/`attribute` knobs) is the one that actually drives the shell. (Investigated as the task flagged a possible "Topbar useTheme without ThemeProvider crashes" latent bug — that specific crash does NOT occur, because `createArqelApp` always provides the `react/providers` ThemeProvider; so the latent-crash hypothesis is refuted, but this deeper duplication is the real finding.)

**Suggested framework fix:** collapse to ONE theme system. Either (a) have `@arqel-dev/react/providers` re-export / delegate to `@arqel-dev/theme` (single `ThemeContext`), and make the shell `Topbar` import `useTheme` from `@arqel-dev/theme`; or (b) drop the standalone `@arqel-dev/theme` provider/toggle and keep only `react/providers`, moving the richer `storageKey`/`attribute` options + the `preventFlashScript` helper onto it. Until then, document loudly that `@arqel-dev/theme`'s ThemeProvider/ThemeToggle are NOT what `@arqel-dev/ui/shell` uses.

**Distinct-from:** unrelated to #1–#4 (morph-map, action-closure-null, workflow-no-HTTP-endpoint, Field::make factory). This is a frontend provider-duplication issue in the JS packages, not a PHP serialization/factory issue.

## CANDIDATE #6 — no trashed/soft-delete filter primitive in the table package; every soft-deleted Resource hand-rolls its own (MEDIUM)

**Surfaced:** Task 5.0 (OrderResource trashed filter, Phase-5 soft-delete E2E prerequisite).
**Severity (suspected):** MEDIUM — not a crash, but a recurring boilerplate gap: any Resource backed by a `SoftDeletes` model that wants to surface/toggle trashed rows in its index must hand-write a filter, because the framework ships none.

**The gap:** `packages/table/src/Filters/` ships SelectFilter, MultiSelectFilter, TernaryFilter, TextFilter, DateRangeFilter, ScopeFilter, QueryBuilderFilter — but NO TrashedFilter/SoftDeleteFilter. `grep -rn "withTrashed\|onlyTrashed\|SoftDelete" packages/table/src packages/core/src` → ZERO matches. So nothing in the framework knows how to relax the `SoftDeletes` global scope for a table query. The `Resource` base / `InertiaDataBuilder` index query also never calls `withTrashed()`, so trashed rows are simply invisible with no built-in way to reveal them.

**Reproduction:** OrderResource uses `App\Models\Order` (SoftDeletes). The seeder creates 20 active + 5 soft-deleted orders. With only the framework primitives there is no declarative way to let the admin view those 5 trashed rows — the SoftDeletes default scope hides them and no shipped filter overrides it.

**App-side workaround (taken):** a plain `SelectFilter::make('trashed')` whose `->apply(Closure)` calls `$query->onlyTrashed()` (value `'only'`) or `$query->withTrashed()` (value `'with'`); unset leaves the scope intact. This works because `Filter::apply()` hands the raw Eloquent `Builder` (with the SoftDeletes scope still attached) to the closure, and `with/onlyTrashed()` are scope macros that remove/replace it. It's ~20 lines of per-Resource boilerplate that every soft-deleted Resource would have to repeat verbatim.

**Suggested framework fix:** ship a first-class `TrashedFilter` (Filament-style: a select/ternary with `with-trashed` / `only-trashed` / `without-trashed` states) in `packages/table/src/Filters/`, that detects the model uses `SoftDeletes` and applies `withTrashed()`/`onlyTrashed()` itself. Bonus: a `Resource`/`Table` opt-in (e.g. `->softDeletes()`) that auto-registers it + the restore/force-delete row actions.

**Classification note for Round 22:** distinct from #1–#5. This is a genuine missing-primitive gap in the table package (a feature the framework plausibly should ship for any SoftDeletes resource), not app-misuse — the app could not express "show trashed" with any shipped filter and had to drop to a raw closure. A strict verifier may still weigh it as a deferred-enhancement rather than a defect; either way the absence is real and forces per-Resource boilerplate.

## CANDIDATE #7 — three Phase-5 E2E renderer/router gaps: (A) dropdown row actions bypass ConfirmDialog/ActionFormModal and POST to a non-existent action endpoint (404), (B) duplicate field names in a Form drop their controls, (C) the `admin/{resource}` route's allowlist only excludes auth slugs, shadowing app-defined `/admin/*` routes

**Surfaced:** Tasks 5.1/5.2 (Phase-5 E2E for custom actions, workflow, and the versioning-UI demo page).
**Severity (suspected):** A = HIGH, B = MEDIUM, C = MEDIUM. Three distinct defects found while writing the E2E specs; each is verified against the running dogfood stack (not a selector mismatch). All three forced an app-side workaround or a "assert-what-renders + document-the-gap" spec.

### (A) Row actions rendered in the dropdown ActionMenu bypass ConfirmDialog/ActionFormModal and POST to a non-existent `/arqel-dev/actions/{name}` endpoint → 404

**Severity (suspected): HIGH** — custom row actions are *silently broken* whenever a row exceeds the inline action threshold (>3 actions), which is the common case once an app adds even two custom actions on top of the built-in Edit/Delete.

**The root cause (precise):** This is NOT "the modal loads an iframe". The actual mechanism is:
- A row's actions render through `<ActionMenu>` (`packages-js/ui/src/action/ActionMenu.tsx`). When the action count is `<= inlineThreshold` (default **3**) they render inline as `<ActionButton>`s — which DO mount `ConfirmDialog` (for `requiresConfirmation()`) and `ActionFormModal` (for `->form([...])`). But when the count **exceeds 3**, `<ActionMenu>` collapses them into a Radix dropdown, and each `DropdownMenuItem`'s `onSelect` calls `onInvoke(action)` **directly** — bypassing `ConfirmDialog`/`ActionFormModal` entirely (the menu's own comment even says "the menu is intended for plain links / direct invocations").
- `invokeAction()` (`packages-js/ui/src/pages/ArqelIndexPage.tsx:53-71`) then computes the URL as `action.url ? ... : `/arqel-dev/actions/${action.name}`` and POSTs there. For a custom `RowAction` with an `->action(Closure)` (and no `->url()`), `Action::toArray()` emits NO `url` (only `view/edit/delete/restore` rows and bulk actions get a `resolveStockUrl()` URL), so the fallback `/arqel-dev/actions/{name}` is used — **an endpoint that does not exist anywhere in the framework's routes**. The POST 404s, and Inertia renders that 404 response into a native `<dialog>`/`<iframe>` error overlay.

PostResource's row has FOUR actions (Edit / Delete / Publish / Change Status) > 3, so it takes the dropdown path. Result:
- `publish` never shows its `requiresConfirmation()` confirm/cancel UI — clicking it POSTs `/arqel-dev/actions/publish` → 404.
- `change_status` never shows its `SelectField('status')` `ActionFormModal` — clicking it POSTs `/arqel-dev/actions/change_status` → 404.

The bulk `archive` BulkAction is wired differently (bulk actions get a `resolveStockUrl()` `POST /admin/{slug}/bulk/{name}` URL) and the bulk-action bar renders + functions; only the per-row dropdown action *execution* is broken.

**Evidence:** `ActionMenu.tsx:50-65` (inline ≤3 path mounts `ActionButton`) vs `:77-93` (dropdown path calls `onInvoke(action)` directly on `onSelect`); `ArqelIndexPage.tsx:59-61` (`/arqel-dev/actions/${action.name}` fallback URL); `grep -rn "arqel-dev/actions" packages/*/routes` → no such route. Live: clicking Publish/Change Status in the dropdown issues `POST /arqel-dev/actions/{name}` which returns **404** (captured via `page.waitForResponse`); the 404 is what Inertia paints into the native dialog/iframe overlay.

**Why it matters:** custom RowActions with `requiresConfirmation()` or `->form([...])` are a headline feature; as soon as a row has more than 3 actions the dropdown path silently bypasses BOTH modals and hits a missing endpoint — so neither the confirmation nor the inline action-form ever renders, and the action does not execute.

**Suggested framework fix:** (1) route the dropdown `DropdownMenuItem` `onSelect` through the same `ActionButton`/`ConfirmDialog`/`ActionFormModal` flow as the inline path (don't bypass confirmation/form for actions that declare them); and/or (2) add the missing action-execution endpoint (e.g. `POST /admin/{resource}/{id}/actions/{name}`) and have `Action::toArray()` emit a real `url` for custom row actions with an `->action(Closure)` instead of leaving the React side to fall back to a non-existent `/arqel-dev/actions/{name}`.

**Spec impact:** `05-actions.spec.ts` now HONESTLY asserts the broken state: the dropdown surfaces the Publish/Change Status menuitems (true), and activating either POSTs to `/arqel-dev/actions/{name}` and gets a **404** (captured via `waitForResponse`). When Round 22 fixes this, those two tests will FAIL — the intended regression signal forcing the spec to assert the working confirmation/form modal.

### (B) Two Form fields sharing one name (`status`) → both render as a bare `<label>` with no control

**The gap:** TicketResource's `form()` mounts BOTH `Field::select('status')->options(...)` AND `StateTransitionField::make('status')->showHistory()` (two components, same field name `status`). On the edit form, the Subject `TextField` renders its `<input>` and Save/Cancel render fine, but **both `status` nodes render only `<label>Status</label>` with NO `<select>`, NO combobox, NO transition buttons, NO history** — there are `0` `<select>` elements on the entire ticket edit form, and the option labels ("Open"/"Resolved") never appear in the DOM.

**Evidence:** `[data-arqel-field="status"]` resolves to 2 nodes; each `.innerHTML` is just the label element. By contrast, PostResource's single `Field::select('status')` renders a working native `<select>` on its create form (spec 04/06), proving SelectField itself renders correctly when its name is unique. So the trigger is the **duplicate `data-arqel-field="status"`** (the plain select + the StateTransitionField collide; the control is dropped). This compounds the pre-existing CANDIDATE #3 sub-point (StateTransitionField emits no transition UI without a bound record).

**Spec impact:** `08-workflow.spec.ts` asserts the editable form surfaces (Subject input + the two Status labels + Save/Cancel) and documents that the status control / transition UI does not render — rather than forcing a flaky assertion on a control that is absent.

### (C) The `admin/{resource}` route is constrained ONLY against an auth-slug allowlist, not against registered resources / app routes → it still shadows app-defined single-segment `/admin/*` routes → 404

**The gap:** Arqel core registers a `GET admin/{resource}` route (`arqel.resources.index`) directly in `ArqelServiceProvider::packageBooted()` (via `registerResourceRoutes()` → the `packages/core/routes/arqel.php` group; this is the provider's boot phase, a direct call — NOT an `app->booted()` callback), which is inserted into the route collection **before** routes declared in the app's `routes/web.php` or in a normal app provider `boot()`.

The `{resource}` parameter IS constrained — `->where('resource', $reservedSlugs)` where `$reservedSlugs = '(?!(?:login|logout|register|forgot-password|reset-password|email|dashboards)(?:/|$))[^/]+'` (`packages/core/routes/arqel.php:27`). But that allowlist excludes ONLY the **auth slugs** (login / logout / register / forgot-password / reset-password / email / dashboards); it does NOT exclude registered resources, nor any app-defined single-segment `/admin/*` route. So a custom path like `/admin/versions-demo` still matches `admin/{resource}` with `resource="versions-demo"`, the ResourceController finds no such resource, and returns a hard **404** — even though `route:list` shows the custom route registered. (It is *constrained*, just not against the right set — calling it "greedy/unconstrained" was imprecise.)

**Evidence:** `Router::getRoutes()->match(Request::create('/admin/versions-demo'))` resolved to `arqel.resources.index` (uri `admin/{resource}`), not the app's `showcase.versions-demo`. The wildcard sits earlier in the collection than the web.php route — Laravel matches by insertion order, and the `where` regex happily admits `versions-demo` (it is not an auth slug), so the wildcard wins. The page therefore 404'd server-side for authenticated users despite the route being defined.

**App-side workaround (taken):** register the `/admin/versions-demo` route in `AppServiceProvider::register()` (AppServiceProvider is listed first in `bootstrap/providers.php`, and `register()` runs before any provider `boot()`/`packageBooted()`), so the static route is inserted into the collection **before** the panel's wildcard and wins the match. Verified: `match()` now resolves `showcase.versions-demo` and the page returns 200. The route was moved out of `routes/web.php` for this reason (a comment there points to AppServiceProvider::register()).

**Suggested framework fix:** widen the `{resource}` constraint to also exclude registered resource slugs / app routes — e.g. build the `where` regex from the actual registered-resources allowlist (only known slugs match), or register the panel routes with a lower priority than app routes — so a single static `/admin/*` route declared in `routes/web.php` is not silently shadowed into a 404. As-is, the auth-slug-only allowlist means every app that wants a custom `/admin/<segment>` page must register it in `register()` (an unobvious requirement) to beat the wildcard.

**Classification note for Round 22:** (A) and (B) are renderer defects (dropdown-actions bypass confirmation/form modal + POST a missing endpoint → 404; duplicate-field-name control drop) — both reproduce against the live stack and are distinct from the deferred-feature framing of CANDIDATE #3. (C) is a routing-precedence trap in the core service provider; arguably a framework-side defect (the `{resource}` constraint allowlists only auth slugs, not registered resources / app routes) rather than app-misuse, since the app's correctly-defined route is silently shadowed with no diagnostic.

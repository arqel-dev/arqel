# Resource quality gaps — unify field source + opt-in auto-discovery

> **Goal:** Close two long-standing Resource friction points in `arqel-dev/core`: (A) validation and rendering must read fields from one effective source, so a layout-aware `form()` no longer forces the developer to re-declare every field in `fields()`; (B) wire the already-implemented `ResourceRegistry::discover()` behind an opt-in config flag, so apps can rely on the auto-discovery the config already advertises.

**Affected package:** `arqel-dev/core` only. No new dependency. All changes additive/opt-in — existing apps behave exactly as before.

---

## Gap A — `Resource::effectiveFields()` unifies the field source

### Today
`Arqel\Core\Support\InertiaDataBuilder::resolveFormFields()` resolves the form's fields as `form()->getFields()` when `form()` returns a form object (one exposing `getFields()` + `toArray()`), else falls back to `fields()`. But `Arqel\Core\Http\Controllers\ResourceController::extractRules()` always reads `$resource->fields()`. So a Resource with a layout-aware `form()` (where the real field list lives in the form, not in `fields()`) gets its create/edit form rendered from `form()->getFields()` but validated from `fields()`. The two diverge unless the developer duplicates every field into `fields()` as well — the documented BUG-VAL gap.

### Fix
Add one public method to `Arqel\Core\Resources\Resource`:

```php
/**
 * The effective field list for this Resource: the form's fields when a
 * form() schema is declared, otherwise the flat fields(). This is the
 * single source both validation (rule extraction) and rendering read,
 * so a layout-aware form() does not require re-declaring fields().
 *
 * @return array<int, mixed>
 */
public function effectiveFields(): array
{
    $form = $this->form();

    if (is_object($form) && method_exists($form, 'getFields')) {
        $fields = $form->getFields();

        if (is_array($fields)) {
            return array_values($fields);
        }
    }

    return $this->fields();
}
```

Then:
- `ResourceController::extractRules()` reads `$resource->effectiveFields()` instead of `$resource->fields()` (one line — the single `$extractor->extract($resource->fields())` call becomes `$extractor->extract($resource->effectiveFields())`). The surrounding fail-closed logic (returning `null` when `arqel-dev/form` is absent, throwing when the extractor is present-but-broken) is untouched.
- `InertiaDataBuilder::resolveFormFields()` reuses `effectiveFields()` for the field list. It still reads `form()->toArray()` separately for the layout payload — only the field-list branch is replaced, so the rendered `form` payload is unchanged. Concretely, the method keeps returning `[$fields, $formPayload]`, but `$fields` now comes from `$resource->effectiveFields()` (which already encodes the form-vs-flat decision), and `$formPayload` is `form()->toArray()` when a form object is present, else `null`.

### Why a method on Resource (not a service)
`fields()` and `form()` are already methods on `Resource`; the effective-source decision belongs next to them — discoverable, overridable per-Resource, and unit-testable without booting the container. It introduces no new class and no indirection.

### Boundaries / interfaces
- `Resource::effectiveFields(): array` — new public method. Default behavior derives from `form()`/`fields()`; a Resource can override it if it has a bespoke source.
- `ResourceController::extractRules()` — unchanged signature; swaps one accessor.
- `InertiaDataBuilder::resolveFormFields()` — unchanged signature/return shape; field list now sourced via `effectiveFields()`.

---

## Gap B — opt-in resource auto-discovery (BUG-VAL-005)

### Today
`Arqel\Core\Resources\ResourceRegistry::discover(string $path, string $namespace): void` is fully implemented (Symfony Finder walk + reflection, skips abstract/interface/trait, requires `implements HasResource`, registers each into the registry). But nothing calls it. `config/arqel.php` advertises `resources.path` (`app_path('Arqel/Resources')`) and `resources.namespace` (`App\Arqel\Resources`), implying auto-discovery — yet apps must hand-register every Resource via `PanelRegistry::resources([...])`. The `ResourceController` resolves slugs against the `ResourceRegistry`, so a discovered Resource becomes reachable at `/admin/{slug}` without being on a panel.

### Fix
- Add a config flag `resources.discover` to the published `config/arqel.php`, defaulting to `false`:
  ```php
  'resources' => [
      'path' => app_path('Arqel/Resources'),
      'namespace' => 'App\\Arqel\\Resources',
      // When true, the framework scans `path`/`namespace` at boot and
      // registers every HasResource class it finds. Off by default —
      // apps that hand-register via PanelRegistry::resources([...]) are
      // unaffected.
      'discover' => false,
  ],
  ```
- In `ArqelServiceProvider`, inside the existing `$this->app->booted(...)` callback that runs `syncPanelResourcesIntoRegistry()` + `electDefaultCurrentPanel()`, add a guarded discover call BEFORE the sync (so discovered Resources are in the registry when election/sync run):
  ```php
  if ((bool) config('arqel.resources.discover', false)) {
      $path = config('arqel.resources.path');
      $namespace = config('arqel.resources.namespace');
      if (is_string($path) && is_string($namespace)) {
          $this->app->make(ResourceRegistry::class)->discover($path, $namespace);
      }
  }
  ```

### Behavior
- Flag absent or `false` (every existing app): no discovery, identical behavior.
- Flag `true`: at boot, every `HasResource` class under the configured path/namespace is registered, reachable at `/admin/{slug}` without manual registration. Hand-registered Resources still work (registry `register()` is idempotent via `has()` checks where used; `discover` registers fresh classes).

### Boundaries / interfaces
- New config key `arqel.resources.discover` (bool, default false).
- `ArqelServiceProvider::packageBooted()` — adds a guarded discover call inside the existing booted callback. No new public method; `discover()` already exists.

---

## Testing

- **Gap A (unit):** a Resource fixture whose `form()` returns a form object exposing `getFields()` returning fields A,B,C while `fields()` returns only A → `effectiveFields()` returns A,B,C. A Resource with no form() (or a non-form `form()`) → `effectiveFields()` returns `fields()`.
- **Gap A (integration):** through `ResourceController`, a store/update request validates against the rules extracted from the form's fields (a field present only in `form()` is validated), proving validation and rendering share a source. Reuse the existing `InertiaValidationFlowTest`/`MockableResource` patterns.
- **Gap B:** with `config(['arqel.resources.discover' => true, 'arqel.resources.path' => <fixture dir>, 'arqel.resources.namespace' => <fixture ns>])`, re-running the boot discover path populates the `ResourceRegistry` with the fixture Resource; with `discover => false`, it does not. A fixture Resources directory + namespace is needed (a single `HasResource` class).

All existing core suites must stay green — the changes are additive, so no existing test should need to change.

---

## Risk & rollout

- Gap A: `effectiveFields()` defaults to `fields()` whenever `form()` is absent or not a form object, so Resources without a layout-aware form behave identically. The only observable change is that a layout-aware-`form()` Resource now validates the form's fields too — which is the desired fix, and strictly more correct (it previously silently skipped validating form-only fields).
- Gap B: gated behind a default-`false` flag; zero impact unless opted in.
- Ships in the next minor (v0.13.0). No new composer dependency.

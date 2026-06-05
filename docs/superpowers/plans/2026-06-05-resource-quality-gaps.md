# Resource quality gap fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close two Resource friction points in `arqel-dev/core`: a single effective field source for validation + rendering (`Resource::effectiveFields()`), and opt-in resource auto-discovery (`config('arqel.resources.discover')`).

**Architecture:** Both additive/opt-in, scoped to `arqel-dev/core`. Gap A adds one method on `Resource` and swaps one accessor in `ResourceController` + one in `InertiaDataBuilder`. Gap B adds a default-`false` config flag and a guarded `ResourceRegistry::discover()` call in the existing boot callback. No new dependency.

**Tech Stack:** PHP 8.3+, Laravel 12/13, Pest 3, Orchestra Testbench.

**Spec:** [`docs/superpowers/specs/2026-06-05-resource-quality-gaps-design.md`](../specs/2026-06-05-resource-quality-gaps-design.md)

---

## Conventions

- Run core tests: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest`
- Lint: `/home/diogo/PhpstormProjects/arqel/packages/versioning/vendor/bin/pint <files>` (from repo root)
- Commit DCO: `git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "..."`. Stage specific paths, never `git add -A`. A pre-commit hook runs pint/biome; let it run.
- Branch: `feat/resource-quality-gaps` (already created).
- The core package does NOT depend on `arqel-dev/form` — tests use duck-typed form-like stubs (see the existing `packages/core/tests/Feature/FormPayloadIntegrationTest.php` for the `FakeForm`/`FakeField`/`FormDrivenResource` pattern).

---

## File Structure

- **Modify:** `packages/core/src/Resources/Resource.php` — add `effectiveFields()` (Gap A).
- **Modify:** `packages/core/src/Http/Controllers/ResourceController.php` — `extractRules()` uses `effectiveFields()` (Gap A).
- **Modify:** `packages/core/src/Support/InertiaDataBuilder.php` — `resolveFormFields()` sources the field list via `effectiveFields()` (Gap A).
- **Create:** `packages/core/tests/Unit/EffectiveFieldsTest.php` — unit test for `effectiveFields()` (Gap A).
- **Modify:** `packages/core/tests/Feature/InertiaValidationFlowTest.php` — integration test that validation uses form fields (Gap A).
- **Modify:** `packages/core/config/arqel.php` — add `resources.discover` flag (Gap B).
- **Modify:** `packages/core/src/ArqelServiceProvider.php` — guarded `discover()` call in the booted callback (Gap B).
- **Create:** `packages/core/tests/Feature/ResourceDiscoveryTest.php` — discovery test (Gap B).
- **Create:** `packages/core/tests/Fixtures/Discoverable/DiscoverableResource.php` — fixture Resource for discovery (Gap B).

---

## Task 1: Gap A — `Resource::effectiveFields()`

**Files:**
- Modify: `packages/core/src/Resources/Resource.php` (add method after `form()`, ~line 156)
- Test: create `packages/core/tests/Unit/EffectiveFieldsTest.php`

- [ ] **Step 1: Write the failing test**

Create `packages/core/tests/Unit/EffectiveFieldsTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Resources\Resource;

/** A duck-typed form-like object exposing getFields(). */
final class EF_FakeForm
{
    /** @param array<int, mixed> $fields */
    public function __construct(private array $fields) {}

    /** @return array<int, mixed> */
    public function getFields(): array
    {
        return $this->fields;
    }
}

/** Resource whose form() may or may not be set, with a flat fields(). */
final class EF_Resource extends Resource
{
    public static string $model = 'stdClass';

    /** @var array<int, mixed> */
    public static array $flat = ['flat-a'];

    public static mixed $formObject = null;

    public function fields(): array
    {
        return self::$flat;
    }

    public function form(): mixed
    {
        return self::$formObject;
    }
}

beforeEach(function (): void {
    EF_Resource::$flat = ['flat-a'];
    EF_Resource::$formObject = null;
});

it('returns the flat fields() when no form is declared', function (): void {
    expect((new EF_Resource)->effectiveFields())->toBe(['flat-a']);
});

it('returns the flat fields() when form() is not a form-like object', function (): void {
    EF_Resource::$formObject = 'not-a-form';
    expect((new EF_Resource)->effectiveFields())->toBe(['flat-a']);
});

it('returns form()->getFields() (re-indexed) when a form is declared', function (): void {
    EF_Resource::$formObject = new EF_FakeForm([2 => 'form-x', 5 => 'form-y']);
    expect((new EF_Resource)->effectiveFields())->toBe(['form-x', 'form-y']);
});

it('falls back to fields() when form()->getFields() is not an array', function (): void {
    EF_Resource::$formObject = new class
    {
        public function getFields(): mixed
        {
            return null;
        }
    };
    expect((new EF_Resource)->effectiveFields())->toBe(['flat-a']);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest tests/Unit/EffectiveFieldsTest.php`
Expected: FAIL — `Call to undefined method ...::effectiveFields()`.

- [ ] **Step 3: Implement `effectiveFields()`**

In `packages/core/src/Resources/Resource.php`, add this method immediately after the `form()` method (which ends around line 156, returning `null`):

```php
    /**
     * The effective field list for this Resource: the form's fields when
     * a form() schema is declared, otherwise the flat fields(). This is
     * the single source both validation (rule extraction) and rendering
     * read, so a layout-aware form() does not require re-declaring every
     * field in fields().
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

- [ ] **Step 4: Run to verify pass**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest tests/Unit/EffectiveFieldsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Lint + commit**

Run: `/home/diogo/PhpstormProjects/arqel/packages/versioning/vendor/bin/pint packages/core/src/Resources/Resource.php packages/core/tests/Unit/EffectiveFieldsTest.php`
```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/core/src/Resources/Resource.php packages/core/tests/Unit/EffectiveFieldsTest.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "feat(core): add Resource::effectiveFields()

Returns form()->getFields() when a layout-aware form is declared, else
the flat fields(). The single field source that validation and rendering
will both read, so a form() no longer forces re-declaring fields()."
```

---

## Task 2: Gap A — `ResourceController` validates `effectiveFields()`

**Files:**
- Modify: `packages/core/src/Http/Controllers/ResourceController.php` (method `extractRules`, the `$extractor->extract(...)` call, ~line 332)
- Test: modify `packages/core/tests/Feature/InertiaValidationFlowTest.php`

- [ ] **Step 1: Write the failing test**

Append to `packages/core/tests/Feature/InertiaValidationFlowTest.php` a test proving the controller extracts rules from the form's fields. This needs the real `Arqel\Form\FieldRulesExtractor` — which the core test bench does NOT have. Instead, assert at the `effectiveFields` boundary: that the controller's rule source is `effectiveFields()`. The cleanest seam is a Resource whose `fields()` and `form()->getFields()` differ, run through the controller's `validated()` path with a stubbed extractor.

Read the existing file first to match its style and the `MockableResource` import. Then append:

```php
it('extracts validation rules from the form fields, not just fields()', function (): void {
    // A form-like object whose getFields() differs from fields().
    $form = new class
    {
        public function getFields(): array
        {
            return ['form-only-field'];
        }
    };

    $resource = Mockery::mock(MockableResource::class)->makePartial();
    $resource->shouldReceive('form')->andReturn($form);
    $resource->shouldReceive('fields')->andReturn(['flat-only-field']);

    // effectiveFields() is a concrete method on Resource; the partial mock
    // runs the real one, which must pick the form's fields.
    expect($resource->effectiveFields())->toBe(['form-only-field']);
});
```

(This asserts the controller's chosen source — `effectiveFields()` — resolves to the form fields. The end-to-end rule extraction is covered in `arqel-dev/form`; here we lock in that the controller reads `effectiveFields()`, which the next step makes it do.)

- [ ] **Step 2: Run to verify it passes already at the Resource layer, then confirm the controller swap**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest tests/Feature/InertiaValidationFlowTest.php`
Expected: the new test PASSES (it exercises `Resource::effectiveFields()` from Task 1). This test guards the contract; Step 3 wires the controller to honor it.

- [ ] **Step 3: Swap the accessor in `extractRules`**

In `packages/core/src/Http/Controllers/ResourceController.php`, find the single line inside `extractRules()`:

```php
            $rules = $extractor->extract($resource->fields());
```

Change `$resource->fields()` to `$resource->effectiveFields()`:

```php
            $rules = $extractor->extract($resource->effectiveFields());
```

Leave everything else in `extractRules()`/`validated()` (the fail-closed null/throw logic) unchanged.

- [ ] **Step 4: Run the full core suite**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest`
Expected: all green. The existing `InertiaValidationFlowTest` "permissive fallback" + "fails closed" tests still pass (they use a `MockableResource` whose `fields()`/`form()` resolve via `effectiveFields()` to the same flat list, since `MockableResource::form()` returns null by default).

- [ ] **Step 5: Lint + commit**

Run: `/home/diogo/PhpstormProjects/arqel/packages/versioning/vendor/bin/pint packages/core/src/Http/Controllers/ResourceController.php packages/core/tests/Feature/InertiaValidationFlowTest.php`
```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/core/src/Http/Controllers/ResourceController.php packages/core/tests/Feature/InertiaValidationFlowTest.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "feat(core): validate from Resource::effectiveFields()

ResourceController::extractRules() now sources rules from
effectiveFields() instead of fields(), so a layout-aware form()'s fields
are validated without being re-declared in fields(). Fail-closed logic
unchanged."
```

---

## Task 3: Gap A — `InertiaDataBuilder` sources fields via `effectiveFields()`

**Files:**
- Modify: `packages/core/src/Support/InertiaDataBuilder.php` (method `resolveFormFields`, ~line 312)

- [ ] **Step 1: Confirm current behavior is covered**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest tests/Feature/FormPayloadIntegrationTest.php`
Expected: PASS (this test asserts the builder picks `Form::getFields()` over `Resource::fields()` and emits the `form` payload). It is the regression guard for this task — it must stay green after the refactor.

- [ ] **Step 2: Refactor `resolveFormFields` to reuse `effectiveFields()`**

In `packages/core/src/Support/InertiaDataBuilder.php`, the current method is:

```php
    private function resolveFormFields(Resource $resource): array
    {
        $form = $resource->form();

        if (is_object($form)
            && method_exists($form, 'getFields')
            && method_exists($form, 'toArray')
        ) {
            $fields = $form->getFields();
            $payload = $form->toArray();

            $normalisedFields = is_array($fields) ? array_values($fields) : $resource->fields();
            $normalisedPayload = null;
            if (is_array($payload)) {
                $normalisedPayload = [];
                foreach ($payload as $key => $value) {
                    $normalisedPayload[(string) $key] = $value;
                }
            }

            return [$normalisedFields, $normalisedPayload];
        }

        return [$resource->fields(), null];
    }
```

Replace it with a version that sources the field list from `effectiveFields()` and only computes the `toArray()` payload here:

```php
    private function resolveFormFields(Resource $resource): array
    {
        $fields = $resource->effectiveFields();
        $form = $resource->form();

        if (is_object($form) && method_exists($form, 'toArray')) {
            $payload = $form->toArray();

            $normalisedPayload = null;
            if (is_array($payload)) {
                $normalisedPayload = [];
                foreach ($payload as $key => $value) {
                    $normalisedPayload[(string) $key] = $value;
                }
            }

            return [$fields, $normalisedPayload];
        }

        return [$fields, null];
    }
```

(Behavior is identical: when `form()` exposes both `getFields()` and `toArray()`, `effectiveFields()` returns `form()->getFields()` re-indexed and the payload is `toArray()`; otherwise `effectiveFields()` returns `fields()` and the payload is null. Note a subtle widening: previously the `form` payload required BOTH `getFields` and `toArray`; now `effectiveFields()` already handles the `getFields` decision, and the payload only requires `toArray`. A form object with `toArray` but no `getFields` would now emit a payload while its fields fall back to `fields()` — acceptable and more permissive, but confirm `FormPayloadIntegrationTest` still passes, which uses a stub with both methods.)

- [ ] **Step 3: Run the regression test + full suite**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest tests/Feature/FormPayloadIntegrationTest.php`
Expected: PASS (all of its cases — no-form, form-with-fields, edit).
Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest`
Expected: all green.

- [ ] **Step 4: Lint + commit**

Run: `/home/diogo/PhpstormProjects/arqel/packages/versioning/vendor/bin/pint packages/core/src/Support/InertiaDataBuilder.php`
```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/core/src/Support/InertiaDataBuilder.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "refactor(core): InertiaDataBuilder sources fields via effectiveFields()

resolveFormFields() now reads the field list from Resource::effectiveFields()
(the same source validation uses) and only computes the form toArray()
payload locally. Rendering and validation are now guaranteed to use one
field source."
```

---

## Task 4: Gap B — `resources.discover` config flag

**Files:**
- Modify: `packages/core/config/arqel.php` (the `resources` block, ~lines 8-11)

- [ ] **Step 1: Add the flag**

In `packages/core/config/arqel.php`, change the `resources` block from:

```php
    'resources' => [
        'path' => app_path('Arqel/Resources'),
        'namespace' => 'App\\Arqel\\Resources',
    ],
```

to:

```php
    'resources' => [
        'path' => app_path('Arqel/Resources'),
        'namespace' => 'App\\Arqel\\Resources',

        // When true, the framework scans `path`/`namespace` at boot and
        // registers every HasResource class it finds, making them
        // reachable at `/admin/{slug}` without manual registration. Off
        // by default — apps that hand-register via
        // PanelRegistry::resources([...]) are unaffected.
        'discover' => false,
    ],
```

- [ ] **Step 2: Validate the config still parses**

Run: `cd /home/diogo/PhpstormProjects/arqel && php -r "require 'packages/core/config/arqel.php';" 2>&1 || echo "needs app() helpers"`
Expected: it may warn about `app_path()` outside an app — that's fine; the lint step below catches syntax issues. Alternatively rely on Step in Task 5 (the discovery test boots a full app and reads this config).

- [ ] **Step 3: Lint + commit**

Run: `/home/diogo/PhpstormProjects/arqel/packages/versioning/vendor/bin/pint packages/core/config/arqel.php`
```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/core/config/arqel.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "feat(core): add resources.discover config flag (default false)

Advertises opt-in auto-discovery; wired in the next commit. Default
false keeps every existing app unaffected."
```

---

## Task 5: Gap B — wire `discover()` in the boot callback

**Files:**
- Modify: `packages/core/src/ArqelServiceProvider.php` (the `$this->app->booted(...)` callback containing `syncPanelResourcesIntoRegistry()` + `electDefaultCurrentPanel()`, ~lines 98-101)
- Create: `packages/core/tests/Fixtures/Discoverable/DiscoverableResource.php`
- Create: `packages/core/tests/Feature/ResourceDiscoveryTest.php`

- [ ] **Step 1: Create the fixture Resource**

Create `packages/core/tests/Fixtures/Discoverable/DiscoverableResource.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Discoverable;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\Stub;

final class DiscoverableResource extends Resource
{
    public static string $model = Stub::class;

    public static ?string $slug = 'discoverables';

    public function fields(): array
    {
        return [];
    }
}
```

(Confirm `Arqel\Core\Tests\Fixtures\Models\Stub` exists — it is used by other core tests. If the namespace for fixtures differs, match the existing `tests/Fixtures` autoload namespace, which is `Arqel\Core\Tests\Fixtures\`.)

- [ ] **Step 2: Write the failing test**

Create `packages/core/tests/Feature/ResourceDiscoveryTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Tests\Fixtures\Discoverable\DiscoverableResource;

it('does not auto-discover Resources when resources.discover is false', function (): void {
    config([
        'arqel.resources.discover' => false,
        'arqel.resources.path' => __DIR__.'/../Fixtures/Discoverable',
        'arqel.resources.namespace' => 'Arqel\\Core\\Tests\\Fixtures\\Discoverable',
    ]);

    $registry = app(ResourceRegistry::class);
    $registry->clear();

    // Re-run the discover gate the provider runs in its booted callback.
    $provider = app()->getProvider(\Arqel\Core\ArqelServiceProvider::class);
    $method = new ReflectionMethod($provider, 'discoverResourcesIfEnabled');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect($registry->has(DiscoverableResource::class))->toBeFalse();
});

it('auto-discovers Resources when resources.discover is true', function (): void {
    config([
        'arqel.resources.discover' => true,
        'arqel.resources.path' => __DIR__.'/../Fixtures/Discoverable',
        'arqel.resources.namespace' => 'Arqel\\Core\\Tests\\Fixtures\\Discoverable',
    ]);

    $registry = app(ResourceRegistry::class);
    $registry->clear();

    $provider = app()->getProvider(\Arqel\Core\ArqelServiceProvider::class);
    $method = new ReflectionMethod($provider, 'discoverResourcesIfEnabled');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect($registry->has(DiscoverableResource::class))->toBeTrue();
});
```

- [ ] **Step 3: Run to verify failure**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest tests/Feature/ResourceDiscoveryTest.php`
Expected: FAIL — `discoverResourcesIfEnabled` method does not exist yet.

- [ ] **Step 4: Extract a `discoverResourcesIfEnabled()` method and call it in the booted callback**

In `packages/core/src/ArqelServiceProvider.php`, the existing booted callback looks like:

```php
        $this->app->booted(function (): void {
            $this->syncPanelResourcesIntoRegistry();
            $this->electDefaultCurrentPanel();
        });
```

Change it to discover BEFORE sync:

```php
        $this->app->booted(function (): void {
            $this->discoverResourcesIfEnabled();
            $this->syncPanelResourcesIntoRegistry();
            $this->electDefaultCurrentPanel();
        });
```

Then add the method (near `syncPanelResourcesIntoRegistry`, follow the file's `protected function` style):

```php
    /**
     * When `arqel.resources.discover` is enabled, scan the configured
     * resources path/namespace and register every HasResource class into
     * the global ResourceRegistry. No-op by default.
     */
    protected function discoverResourcesIfEnabled(): void
    {
        if (! (bool) config('arqel.resources.discover', false)) {
            return;
        }

        $path = config('arqel.resources.path');
        $namespace = config('arqel.resources.namespace');

        if (! is_string($path) || ! is_string($namespace)) {
            return;
        }

        $this->app->make(ResourceRegistry::class)->discover($path, $namespace);
    }
```

Ensure `Arqel\Core\Resources\ResourceRegistry` is already imported at the top of the file (it is — `syncPanelResourcesIntoRegistry` uses it).

- [ ] **Step 5: Run to verify pass + full suite**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest tests/Feature/ResourceDiscoveryTest.php`
Expected: PASS (both tests).
Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest`
Expected: all green.

- [ ] **Step 6: Lint + commit**

Run: `/home/diogo/PhpstormProjects/arqel/packages/versioning/vendor/bin/pint packages/core/src/ArqelServiceProvider.php packages/core/tests/Feature/ResourceDiscoveryTest.php packages/core/tests/Fixtures/Discoverable/DiscoverableResource.php`
```bash
git -C /home/diogo/PhpstormProjects/arqel add packages/core/src/ArqelServiceProvider.php packages/core/tests/Feature/ResourceDiscoveryTest.php packages/core/tests/Fixtures/Discoverable/DiscoverableResource.php
git -C /home/diogo/PhpstormProjects/arqel commit --signoff -m "feat(core): opt-in resource auto-discovery at boot (BUG-VAL-005)

discoverResourcesIfEnabled() runs ResourceRegistry::discover() over the
configured path/namespace when arqel.resources.discover is true, before
the panel sync. Default false keeps existing apps unaffected; discovered
Resources become reachable at /admin/{slug} without manual registration."
```

---

## Task 6: Final validation

**Files:** none (verification).

- [ ] **Step 1: Run the full core suite**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest`
Expected: fully green (the 4 new EffectiveFields unit tests + 2 discovery tests + 1 validation-flow test, plus the unchanged FormPayloadIntegrationTest and all others).

- [ ] **Step 2: Confirm no behavioral regression in the form payload**

Run: `cd /home/diogo/PhpstormProjects/arqel/packages/core && vendor/bin/pest tests/Feature/FormPayloadIntegrationTest.php tests/Feature/InertiaValidationFlowTest.php tests/Unit/EffectiveFieldsTest.php tests/Feature/ResourceDiscoveryTest.php`
Expected: all green.

- [ ] **Step 3: Proceed to finishing-a-development-branch** (push + PR).

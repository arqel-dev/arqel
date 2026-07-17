# Export Download Ownership Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the IDOR in `arqel/export` so a download is served only to the user who created the export (fail-closed), by persisting export→owner in a new `Export` model + `arqel_exports` table.

**Architecture:** `ExportAction::execute()` records the export (with `owner_user_id` captured from `auth()->id()`) before writing the file, using the record's UUID as the on-disk filename id. `ExportDownloadController::download()` resolves the record via `Export::find()` and serves the file only if the authenticated user owns it — 404 (not 403) on any ownership failure to avoid enumeration. The package stays decoupled from the app's User model: `owner_user_id` is a plain string compared to `auth()->id()`, never a `belongsTo`.

**Tech Stack:** PHP 8.3+, Laravel 12+, Eloquent, Spatie Laravel Package Tools (migration registration), Pest 3 + Orchestra Testbench.

## Global Constraints

- `declare(strict_types=1);` in every PHP file.
- Classes `final` unless extensibility is design intent.
- `@internal` docblock tag on new public classes (ADR-019 — the export package is internal API).
- No `belongsTo(User)` / no import of the app's User class — decouple via `auth()->id()` (`int|string|null`) and `getAuthIdentifier()`.
- Migration registered via Spatie `->hasMigration('create_arqel_exports_table')` in `configurePackage()` — NEVER `loadMigrationsFrom` (avoids the double-load gotcha).
- Table name `arqel_exports` (prefix coherent with `arqel_marketplace_*`).
- Fail-closed: missing record, `owner_user_id === null`, or owner ≠ authenticated → `abort(404)`.
- 404 not 403 on ownership failure (anti-enumeration).
- Tests are mandatory; export core coverage target ≥90%.
- Commits: Conventional Commits + DCO sign-off (`git commit --signoff`), reference `#381` in body. Subagent commits use `--no-verify` (Pint runs from repo root separately).

---

### Task 1: `arqel_exports` migration + `Export` model + provider registration

**Files:**
- Create: `packages/export/database/migrations/2026_07_17_000000_create_arqel_exports_table.php`
- Create: `packages/export/src/Models/Export.php`
- Modify: `packages/export/src/ExportServiceProvider.php:25-30` (add `->hasMigration(...)`)
- Test: `packages/export/tests/Models/ExportModelTest.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces:
  - Table `arqel_exports` with columns: `id` (uuid, PK), `owner_user_id` (string, nullable, indexed), `format` (string 16), `path` (string), `expires_at` (timestamp, nullable, indexed), `created_at`/`updated_at`.
  - `Arqel\Export\Models\Export` — `final`, `$table='arqel_exports'`, `$incrementing=false`, `$keyType='string'`, `$guarded=[]`, `$casts=['expires_at'=>'datetime']`. Mass-assignable keys: `id`, `owner_user_id`, `format`, `path`, `expires_at`. Later tasks call `Export::create([...])` and `Export::find(string $id)`.

- [ ] **Step 1: Write the failing model + migration test**

Create `packages/export/tests/Models/ExportModelTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Export\Models\Export;
use Illuminate\Support\Facades\Schema;

it('creates the arqel_exports table with the expected columns', function (): void {
    expect(Schema::hasTable('arqel_exports'))->toBeTrue();
    expect(Schema::hasColumns('arqel_exports', [
        'id', 'owner_user_id', 'format', 'path', 'expires_at', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('uses a string uuid primary key and casts expires_at', function (): void {
    $export = new Export;

    expect($export->getKeyName())->toBe('id');
    expect($export->getKeyType())->toBe('string');
    expect($export->getIncrementing())->toBeFalse();

    $created = Export::create([
        'id' => '11111111-1111-4111-8111-111111111111',
        'owner_user_id' => '42',
        'format' => 'csv',
        'path' => '/tmp/export-x.csv',
        'expires_at' => null,
    ]);

    expect($created->exists)->toBeTrue();
    expect($created->getCasts()['expires_at'] ?? null)->toBe('datetime');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest packages/export/tests/Models/ExportModelTest.php`
Expected: FAIL — `Class "Arqel\Export\Models\Export" not found` (and/or table missing).

- [ ] **Step 3: Create the migration**

Create `packages/export/database/migrations/2026_07_17_000000_create_arqel_exports_table.php`:

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
        Schema::create('arqel_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('owner_user_id')->nullable()->index();
            $table->string('format', 16);
            $table->string('path');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arqel_exports');
    }
};
```

- [ ] **Step 4: Create the `Export` model**

Create `packages/export/src/Models/Export.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Export\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persisted record of a generated export, binding a file to its owner.
 *
 * `owner_user_id` is stored as a plain string (the stringified
 * `auth()->id()`) so the package stays decoupled from the app's User
 * model — there is deliberately no `belongsTo(User)` relation.
 *
 * @internal Esta classe é interna ao Arqel (ADR-019) e pode mudar em qualquer minor.
 */
final class Export extends Model
{
    protected $table = 'arqel_exports';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
```

- [ ] **Step 5: Register the migration in the provider**

In `packages/export/src/ExportServiceProvider.php`, change the `configurePackage` body:

```php
    public function configurePackage(Package $package): void
    {
        $package
            ->name('arqel-export')
            ->hasMigration('create_arqel_exports_table')
            ->hasRoute('admin');
    }
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `vendor/bin/pest packages/export/tests/Models/ExportModelTest.php`
Expected: PASS (2 passed).

> If the table is not created, the test's Testbench base case must run the package migrations. The export `TestCase` already boots `ExportServiceProvider`; Spatie's `->hasMigration()` makes Testbench load it. If a base `TestCase` needs `$this->loadLaravelMigrations()` or `artisan('migrate')`, mirror the pattern in an existing export feature test (e.g. `packages/export/tests/` sibling that already hits the DB). Do NOT add `loadMigrationsFrom`.

- [ ] **Step 7: Commit**

```bash
git add packages/export/database/migrations/2026_07_17_000000_create_arqel_exports_table.php \
        packages/export/src/Models/Export.php \
        packages/export/src/ExportServiceProvider.php \
        packages/export/tests/Models/ExportModelTest.php
git commit --no-verify --signoff -m "feat(export): add Export model + arqel_exports table

Persists export->owner so downloads can be ownership-gated. Model has no
belongsTo(User) — owner_user_id is a plain string vs auth()->id(). Migration
registered via Spatie ->hasMigration(). Part of #381.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Record the owner on export creation (`ExportAction::execute()`)

**Files:**
- Modify: `packages/export/src/Actions/ExportAction.php:148-174` (the `execute()` body) and imports at `:5-15`
- Test: `packages/export/tests/Actions/ExportCreatesOwnedRecordTest.php`

**Interfaces:**
- Consumes: `Arqel\Export\Models\Export` (Task 1) — `Export::create(['id'=>..., 'owner_user_id'=>..., 'format'=>..., 'path'=>..., 'expires_at'=>null])`.
- Produces: after a non-dry-run `execute()`, an `Export` row exists whose `id` equals the on-disk filename id (`export-<id>.<ext>`), `owner_user_id` equals `(string) auth()->id()` (or `null` when unauthenticated), `format` equals `$this->format->value`, and `path` equals the absolute destination. The returned array shape is unchanged: `array{path,filename,format,mimeType}`.

- [ ] **Step 1: Write the failing test**

Create `packages/export/tests/Actions/ExportCreatesOwnedRecordTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Export\Actions\ExportAction;
use Arqel\Export\ExportFormat;
use Arqel\Export\Models\Export;

it('creates an Export row owned by the authenticated user', function (): void {
    $user = new class extends \Illuminate\Foundation\Auth\User {
        protected $table = 'users';

        public function getAuthIdentifier(): mixed
        {
            return 42;
        }
    };
    $this->be($user);

    $dir = sys_get_temp_dir().'/arqel-export-test-'.uniqid();
    $result = ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns([['key' => 'id', 'label' => 'ID']])
        ->withDestinationDir($dir)
        ->execute([['id' => 1]]);

    $exports = Export::all();
    expect($exports)->toHaveCount(1);

    $export = $exports->first();
    expect($export->owner_user_id)->toBe('42');
    expect($export->format)->toBe('csv');
    expect($export->path)->toBe($result['path']);
    // filename id matches the record id: export-<id>.csv
    expect($result['filename'])->toBe('export-'.$export->id.'.csv');
});

it('stores a null owner when unauthenticated (CLI/guest)', function (): void {
    $dir = sys_get_temp_dir().'/arqel-export-test-'.uniqid();
    ExportAction::make('export')
        ->format(ExportFormat::CSV)
        ->withColumns([['key' => 'id', 'label' => 'ID']])
        ->withDestinationDir($dir)
        ->execute([['id' => 1]]);

    expect(Export::first()->owner_user_id)->toBeNull();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest packages/export/tests/Actions/ExportCreatesOwnedRecordTest.php`
Expected: FAIL — `Export::all()` is empty (no row created yet) / count 0 ≠ 1.

- [ ] **Step 3: Add the import**

In `packages/export/src/Actions/ExportAction.php`, add to the `use` block (after the existing `use Arqel\Export\ExportFormat;` line):

```php
use Arqel\Export\Models\Export;
```

- [ ] **Step 4: Rewrite the `execute()` file-id + record creation**

In `packages/export/src/Actions/ExportAction.php`, replace the id/destination/exporter block inside `execute()` (currently lines 154–166, from the `// UUID id keeps...` comment through the exporter call) with:

```php
        // UUID id keeps filenames collision-free and matches the
        // download controller's route constraint + Export::find lookup,
        // so the produced file is retrievable and ownership-gated (#381).
        $id = Str::uuid()->toString();
        $filename = 'export-'.$id.'.'.$this->format->extension();
        $dir = rtrim($this->destinationDir, '/');
        $destination = $dir.'/'.$filename;

        if (! $this->dryRun) {
            if (! is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
            $this->resolveExporter()->export($record, $this->columns, $destination);

            $ownerId = auth()->id();
            Export::create([
                'id' => $id,
                'owner_user_id' => $ownerId !== null ? (string) $ownerId : null,
                'format' => $this->format->value,
                'path' => $destination,
                'expires_at' => null,
            ]);
        }
```

The `return` statement below is unchanged (`'path' => $this->dryRun ? 'dry-run' : $destination`, `'filename' => $filename`, etc.). Dry-run stays record-free (no I/O, no DB row).

- [ ] **Step 5: Run the test to verify it passes**

Run: `vendor/bin/pest packages/export/tests/Actions/ExportCreatesOwnedRecordTest.php`
Expected: PASS (2 passed).

- [ ] **Step 6: Run the full export suite to check for regressions**

Run: `vendor/bin/pest packages/export`
Expected: PASS. If a pre-existing test that exercises a real (non-dry-run) `execute()` now fails because it runs without a DB (`Export::create` needs the table), fix that specific test to use `->dryRun()` where it only asserts the return payload, or to boot the migration if it asserts persistence. Do NOT weaken the new behavior.

- [ ] **Step 7: Commit**

```bash
git add packages/export/src/Actions/ExportAction.php \
        packages/export/tests/Actions/ExportCreatesOwnedRecordTest.php
git commit --no-verify --signoff -m "feat(export): record export owner on creation

execute() now persists an Export row with owner_user_id = (string) auth()->id()
(null when unauthenticated) before returning. The on-disk filename id equals
the record id so the download controller can resolve + ownership-gate it.
Part of #381.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Enforce ownership in `ExportDownloadController::download()`

**Files:**
- Modify: `packages/export/src/Http/Controllers/ExportDownloadController.php` (docblock `:11-32`, `download()` body `:40-67`, imports `:5-9`)
- Test: `packages/export/tests/Http/ExportOwnershipTest.php`

**Interfaces:**
- Consumes: `Arqel\Export\Models\Export` (Task 1) — `Export::find(string $id)` returns `?Export` with `->owner_user_id` (string|null) and `->path` (string); `Request::user()?->getAuthIdentifier()` for the caller id.
- Produces: `download()` serves the file only when an `Export` record exists AND its `owner_user_id` is non-null AND equals `(string) $request->user()?->getAuthIdentifier()`; every other case is `abort(404)`. Invalid id shape stays `abort(400)`.

- [ ] **Step 1: Write the failing test**

Create `packages/export/tests/Http/ExportOwnershipTest.php`. Adjust the route helper / user factory to match the sibling download test in `packages/export/tests/Http/` if one exists (read it first for the exact `TestCase` + route-registration pattern); the assertions below are the contract:

```php
<?php

declare(strict_types=1);

use Arqel\Export\Models\Export;
use Illuminate\Support\Facades\File;

function makeExportFile(string $id, string $dir): string
{
    File::ensureDirectoryExists($dir);
    $path = $dir.'/export-'.$id.'.csv';
    File::put($path, "id\n1\n");

    return $path;
}

function actingUser(int|string $id): object
{
    return new class($id) extends \Illuminate\Foundation\Auth\User {
        public function __construct(private readonly int|string $identifier)
        {
            parent::__construct();
        }

        public function getAuthIdentifier(): mixed
        {
            return $this->identifier;
        }
    };
}

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/arqel-export-own-'.uniqid();
    config()->set('arqel-export.destination_dir', $this->dir);
});

function urlFor(string $id): string
{
    return '/admin/exports/'.$id.'/download';
}

it('serves the file to its owner', function (): void {
    $id = '11111111-1111-4111-8111-111111111111';
    $path = makeExportFile($id, $this->dir);
    Export::create(['id' => $id, 'owner_user_id' => '7', 'format' => 'csv', 'path' => $path, 'expires_at' => null]);

    $this->be(actingUser(7))
        ->get(urlFor($id))
        ->assertOk();
});

it('returns 404 (not 403) when another user requests the export', function (): void {
    $id = '22222222-2222-4222-8222-222222222222';
    makeExportFile($id, $this->dir);
    Export::create(['id' => $id, 'owner_user_id' => '7', 'format' => 'csv', 'path' => $this->dir.'/export-'.$id.'.csv', 'expires_at' => null]);

    $response = $this->be(actingUser(99))->get(urlFor($id));
    $response->assertNotFound();
    expect($response->getStatusCode())->not->toBe(403);
});

it('returns 404 for an ownerless (legacy/CLI) export', function (): void {
    $id = '33333333-3333-4333-8333-333333333333';
    makeExportFile($id, $this->dir);
    Export::create(['id' => $id, 'owner_user_id' => null, 'format' => 'csv', 'path' => $this->dir.'/export-'.$id.'.csv', 'expires_at' => null]);

    $this->be(actingUser(7))->get(urlFor($id))->assertNotFound();
});

it('returns 404 when no Export record exists for the id', function (): void {
    $id = '44444444-4444-4444-8444-444444444444';
    makeExportFile($id, $this->dir); // file on disk, but no DB row

    $this->be(actingUser(7))->get(urlFor($id))->assertNotFound();
});

it('matches an int user id against a string owner_user_id', function (): void {
    $id = '55555555-5555-4555-8555-555555555555';
    $path = makeExportFile($id, $this->dir);
    Export::create(['id' => $id, 'owner_user_id' => '42', 'format' => 'csv', 'path' => $path, 'expires_at' => null]);

    $this->be(actingUser(42))->get(urlFor($id))->assertOk(); // int 42 vs "42"
    $this->be(actingUser(7))->get(urlFor($id))->assertNotFound();
});

it('returns 400 for a malformed id', function (): void {
    $this->be(actingUser(7))->get(urlFor('NOT VALID'))->assertStatus(400);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/pest packages/export/tests/Http/ExportOwnershipTest.php`
Expected: FAIL — the cross-user case returns 200 (glob still serves the file: the bug), and no-record / ownerless cases return 200 too.

- [ ] **Step 3: Add the import**

In `packages/export/src/Http/Controllers/ExportDownloadController.php`, add to the `use` block:

```php
use Arqel\Export\Models\Export;
```

- [ ] **Step 4: Rewrite `download()` to resolve via the record and gate ownership**

Replace the `download()` method body (lines 40–67) with:

```php
    public function download(string $exportId, Request $request): BinaryFileResponse
    {
        if (preg_match(self::UUID_PATTERN, $exportId) !== 1) {
            abort(400, $this->message('arqel::messages.export.invalid_id', 'Invalid export id.'));
        }

        $export = Export::find($exportId);
        if ($export === null) {
            abort(404, $this->message('arqel::messages.export.not_found', 'Export not found.'));
        }

        $userId = $request->user()?->getAuthIdentifier();
        if ($export->owner_user_id === null
            || $userId === null
            || (string) $export->owner_user_id !== (string) $userId) {
            // 404 (not 403) so we never confirm the existence of another
            // user's export — fail-closed, anti-enumeration.
            abort(404, $this->message('arqel::messages.export.not_found', 'Export not found.'));
        }

        $filePath = $export->path;
        if (! is_string($filePath) || ! is_file($filePath)) {
            abort(404, $this->message('arqel::messages.export.not_found', 'Export not found.'));
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $format = ExportFormat::tryFrom(strtolower($extension));

        $headers = [];
        if ($format !== null) {
            $headers['Content-Type'] = $format->mimeType();
        }

        return response()->download($filePath, basename($filePath), $headers);
    }
```

The `$this->resolveDirectory()` helper is now unused by `download()`. Leave it only if another method uses it; otherwise delete `resolveDirectory()` (lines 85–95) to avoid dead code — verify with `grep -n resolveDirectory packages/export/src/Http/Controllers/ExportDownloadController.php` before deleting.

- [ ] **Step 5: Rewrite the class docblock (remove the "no ownership" disclaimer)**

Replace the docblock (lines 11–32) with:

```php
/**
 * Serves a previously generated export file to its owner.
 *
 * Resolves the `Export` record by id, then enforces ownership: the file
 * is served only when the record exists, has a non-null owner, and that
 * owner matches the authenticated user. Any ownership failure — missing
 * record, ownerless (legacy/CLI) export, or a different user — returns
 * 404 (not 403) so the existence of another user's export is never
 * confirmed (fail-closed, anti-enumeration).
 *
 * The bundled `routes/admin.php` gates the route with `web + auth`;
 * ownership is enforced here, in the package, so consumer apps no longer
 * need a bespoke policy to prevent cross-user downloads.
 *
 * @internal Esta classe é interna ao Arqel (ADR-019) e pode mudar em qualquer minor.
 */
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `vendor/bin/pest packages/export/tests/Http/ExportOwnershipTest.php`
Expected: PASS (6 passed).

- [ ] **Step 7: Run the full export suite for regressions**

Run: `vendor/bin/pest packages/export`
Expected: PASS. Any existing download test that fetched a file WITHOUT creating an `Export` row will now 404 — update it to `Export::create([...])` the record first (that is the correct new contract), not to relax the controller.

- [ ] **Step 8: Commit**

```bash
git add packages/export/src/Http/Controllers/ExportDownloadController.php \
        packages/export/tests/Http/ExportOwnershipTest.php
git commit --no-verify --signoff -m "fix(export): enforce download ownership (IDOR) (#381)

ExportDownloadController served any export by UUID to any authenticated
user (IDOR). It now resolves the Export record and serves the file only to
its owner; missing/ownerless/wrong-owner all return 404 (not 403) to avoid
enumeration. Closes #381.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Root-level lint/static-analysis + open the PR

**Files:** none (verification + PR).

**Interfaces:**
- Consumes: the three prior commits on branch `fix/381-export-ownership`.
- Produces: a clean Pint run and an open PR referencing #381.

- [ ] **Step 1: Run Pint from repo root**

Run: `composer run lint` (or `vendor/bin/pint --config pint.json packages/export`)
Expected: no style violations (Pint auto-fixes; if it changes files, `git add` + amend the relevant commit or add a `style(export)` commit).

- [ ] **Step 2: Run PHPStan on the export package (if wired)**

Run: `vendor/bin/phpstan analyse packages/export/src`
Expected: no new errors. If PHPStan reports the pre-existing baseline noise unrelated to these files, ignore it; fix only errors in the files this plan touched.

- [ ] **Step 3: Run the full export suite one final time**

Run: `vendor/bin/pest packages/export`
Expected: PASS (all green).

- [ ] **Step 4: Push and open the PR**

```bash
git push -u origin fix/381-export-ownership
gh pr create --title "fix(export): enforce download ownership (IDOR) (#381)" \
  --body "$(cat <<'EOF'
## Summary
Fixes the IDOR in `arqel/export`: `ExportDownloadController` served any export file by UUID to any authenticated user. Downloads are now ownership-gated in the package.

- New `Export` model + `arqel_exports` table records `owner_user_id` (captured via `auth()->id()`, stored as a string — no `belongsTo(User)`, package stays User-model-agnostic).
- `ExportAction::execute()` persists the owning record before returning; the on-disk filename id equals the record id.
- `ExportDownloadController::download()` resolves the record and serves the file only to its owner. Missing / ownerless (legacy/CLI) / wrong-owner all return **404 (not 403)** — fail-closed, anti-enumeration.

## Out of scope (follow-up)
Expiry cleanup (the `expires_at` column exists but no reaper job), a "my exports" listing UI, and signed URLs.

## Tests
- `ExportModelTest` — uuid PK, cast, table shape.
- `ExportCreatesOwnedRecordTest` — owner captured (int→string), null owner when unauthenticated.
- `ExportOwnershipTest` — owner serves; **cross-user → 404 (the bug proof)**; ownerless → 404; no-record → 404; int-vs-string match; malformed id → 400.

Closes #381.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

- [ ] **Step 5: Report the PR URL back to the user for review (do NOT merge — merge requires user confirmation).**

---

## Self-Review

**1. Spec coverage:**
- Persistence (Export model + table) → Task 1. ✅
- Owner capture via `auth()->id()` (int|string|null, no User coupling) → Task 2. ✅
- Fail-closed 3 conditions + 404-not-403 + `(string)` compare + `getAuthIdentifier()` + docblock rewrite → Task 3. ✅
- Sync-only wiring (no ProcessExportJob) → Task 2 touches only `execute()`. ✅
- Tests: ExportOwnershipTest / ExportCreatesOwnedRecordTest / ExportModelTest / migration test → Tasks 1 & 2 & 3. ✅
- Migration via Spatie `->hasMigration()` → Task 1 Step 5. ✅
- Out-of-scope (expiry cleanup, listing, signed URLs) → documented in PR body, no task. ✅

**2. Placeholder scan:** no TBD/TODO/"handle edge cases"; every code step shows full code. The one soft spot ("adjust route helper to match the sibling test") is bounded by "read it first for the exact pattern; the assertions are the contract" — an integration-detail the implementer resolves from an existing file, not an invented API. ✅

**3. Type consistency:** `Export::create([...])` keys (`id`, `owner_user_id`, `format`, `path`, `expires_at`) identical across Tasks 1/2/3. `owner_user_id` is string|null everywhere; compared with `(string)` cast on both sides. `$export->path` (string) produced in Task 2, consumed in Task 3. Filename shape `export-<id>.<ext>` consistent. ✅

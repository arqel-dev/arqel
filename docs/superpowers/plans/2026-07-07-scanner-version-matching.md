# SecurityScanner Version Matching Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `SecurityScanner` only flag a plugin as vulnerable when its installed version actually falls within an advisory's affected-version range, fixing false-positive auto-delisting.

**Architecture:** Rename `Advisory::fixedIn` → `affectedVersions` (a Composer constraint of affected versions). Add a `VersionMatcher` that wraps `Composer\Semver\Semver::satisfies` with security fail-safes. `SecurityScanner::lookupVulnerabilities` filters each advisory through it before materializing a finding.

**Tech Stack:** PHP 8.3+, `composer/semver` (already transitive; made an explicit require), Pest 3.

## Global Constraints

- `declare(strict_types=1);` in every PHP file; classes `final`.
- Fail-safe always to **affected** (`true`): unknown/empty installed version, empty constraint, or unparseable input must flag the plugin — never assume safe.
- Catch exactly `\UnexpectedValueException | \InvalidArgumentException` around `Semver::satisfies` (the two the `VersionParser` throws). Never catch `\Throwable`.
- Do NOT change the `VulnerabilityDatabase::lookup(package, ecosystem)` contract, nor `rollupSeverity` / `statusFor` / `normalizeSeverity`, nor the auto-delist logic.
- `composer/semver` must be an explicit `require` in `packages/marketplace/composer.json` at `^3.0`.
- Run marketplace tests with `packages/marketplace/vendor/bin/pest`.
- Before pushing: run `composer run lint` from repo ROOT (subagents commit `--no-verify`, so Pint doesn't run at commit — run it explicitly). Pint binary is repo-root `vendor/bin/pint`.
- Commits: Conventional Commits + DCO signoff (`--signoff`), scope `marketplace`, `--no-verify`.
- Spec: `docs/superpowers/specs/2026-07-07-scanner-version-matching-design.md`.

---

### Task 1: `VersionMatcher` with security fail-safes

The isolated, fully-tested unit that decides whether an installed version is affected. No scanner changes yet.

**Files:**
- Create: `packages/marketplace/src/Services/VersionMatcher.php`
- Modify: `packages/marketplace/composer.json` (add `composer/semver` to `require`)
- Test: `packages/marketplace/tests/Unit/Services/VersionMatcherTest.php`

**Interfaces:**
- Produces: `VersionMatcher::isAffected(?string $installed, string $affectedConstraint): bool` — static. Returns `true` (affected) for: version null/empty, constraint empty, or any parse failure; otherwise `Semver::satisfies($installed, $affectedConstraint)`.

- [ ] **Step 1: Add `composer/semver` as an explicit require**

In `packages/marketplace/composer.json`, add to the `require` block (keep alphabetical if the block is sorted):

```json
"composer/semver": "^3.0"
```

It is already present transitively (verified in `vendor/composer/semver`), so no `composer update` is needed for the class to load. If autoload complains, run `composer dump-autoload -d packages/marketplace`.

- [ ] **Step 2: Write the failing test**

Create `packages/marketplace/tests/Unit/Services/VersionMatcherTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Marketplace\Services\VersionMatcher;

it('reports affected when the installed version satisfies the constraint', function () {
    expect(VersionMatcher::isAffected('1.0.0', '<2.0'))->toBeTrue();
    expect(VersionMatcher::isAffected('1.3.0', '>=1.0.1,<1.5'))->toBeTrue();
});

it('reports NOT affected when the installed version is outside the constraint', function () {
    expect(VersionMatcher::isAffected('2.5.0', '<2.0'))->toBeFalse();
    expect(VersionMatcher::isAffected('1.6.0', '>=1.0.1,<1.5'))->toBeFalse();
});

it('fails safe to affected when the installed version is unknown', function () {
    expect(VersionMatcher::isAffected(null, '<2.0'))->toBeTrue();
    expect(VersionMatcher::isAffected('', '<2.0'))->toBeTrue();
    expect(VersionMatcher::isAffected('   ', '<2.0'))->toBeTrue();
});

it('fails safe to affected when the constraint is empty (all versions)', function () {
    expect(VersionMatcher::isAffected('1.0.0', ''))->toBeTrue();
    expect(VersionMatcher::isAffected('1.0.0', '   '))->toBeTrue();
});

it('fails safe to affected when the version or constraint is unparseable', function () {
    expect(VersionMatcher::isAffected('not-a-version', '<2.0'))->toBeTrue();
    expect(VersionMatcher::isAffected('1.0.0', 'garbage!!'))->toBeTrue();
    expect(VersionMatcher::isAffected('1.0.0', '>=1.0.0@badstability'))->toBeTrue();
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `packages/marketplace/vendor/bin/pest tests/Unit/Services/VersionMatcherTest.php`
(from `packages/marketplace/`)
Expected: FAIL — class `VersionMatcher` not found.

- [ ] **Step 4: Implement `VersionMatcher`**

Create `packages/marketplace/src/Services/VersionMatcher.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Marketplace\Services;

use Composer\Semver\Semver;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Decides whether a plugin's installed version falls within an advisory's
 * affected-version constraint.
 *
 * Fails safe to `true` (affected): an unknown installed version, an empty
 * constraint, or an unparseable input must never let a plugin escape the
 * security scanner. A data error flags for human review, it never silently
 * marks a plugin safe.
 */
final class VersionMatcher
{
    /**
     * @param string $affectedConstraint Composer constraint of affected
     *                                    versions (e.g. '<2.0', '>=1.0.1,<1.5').
     */
    public static function isAffected(?string $installed, string $affectedConstraint): bool
    {
        if ($installed === null || trim($installed) === '') {
            return true;
        }

        if (trim($affectedConstraint) === '') {
            return true;
        }

        try {
            return Semver::satisfies($installed, $affectedConstraint);
        } catch (UnexpectedValueException | InvalidArgumentException) {
            return true;
        }
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `packages/marketplace/vendor/bin/pest tests/Unit/Services/VersionMatcherTest.php`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add packages/marketplace/src/Services/VersionMatcher.php packages/marketplace/tests/Unit/Services/VersionMatcherTest.php packages/marketplace/composer.json
git commit --no-verify --signoff -m "feat(marketplace): add VersionMatcher for advisory version ranges

Wraps Composer\\Semver\\Semver::satisfies with security fail-safes: unknown
version, empty constraint, or unparseable input all report affected (true)
so a plugin never escapes the scanner on a data error. composer/semver made
an explicit require. Milestone: SecurityScanner version matching.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Rename `Advisory::fixedIn` → `affectedVersions`

The value-object field gets the correct name and meaning. Update every construction site (the test fakes) so the suite still compiles.

**Files:**
- Modify: `packages/marketplace/src/Contracts/Advisory.php`
- Modify: `packages/marketplace/tests/Unit/Services/SecurityScannerTest.php` (Advisory constructions — 4th arg is positional, so only the docblock/intent changes, values stay)
- Modify: `packages/marketplace/tests/Feature/ScanPluginsCommandTest.php` (one Advisory construction at line ~64)
- Any other `new Advisory(` site — find them all first.

**Interfaces:**
- Produces: `Advisory::__construct(string $id, string $severity, string $summary, string $affectedVersions)` — the 4th public readonly property is now `$affectedVersions` (was `$fixedIn`).

- [ ] **Step 1: Find every `new Advisory(` and every `->fixedIn` usage**

Run (from repo root):
```bash
grep -rn "->fixedIn\|new Advisory(\|public string \$fixedIn\|fixedIn" packages/marketplace
```
Expected: the `Advisory` class definition, the scanner's read of `$advisory->fixedIn` (in `lookupVulnerabilities`), and the test construction sites. Note them — Task 3 handles the scanner read; this task handles the class + test constructions.

- [ ] **Step 2: Rename the field in the value-object**

In `packages/marketplace/src/Contracts/Advisory.php`, change the constructor's 4th parameter and its docblock:

```php
final readonly class Advisory
{
    /**
     * @param string $affectedVersions Composer constraint of the versions this
     *                                  advisory affects (e.g. '<2.0', '>=1.0.1,<1.5').
     *                                  A plugin is vulnerable when its installed
     *                                  version satisfies this constraint.
     */
    public function __construct(
        public string $id,
        public string $severity,
        public string $summary,
        public string $affectedVersions,
    ) {}
}
```

Update the class-level docblock if it references `fixedIn`.

- [ ] **Step 3: Run the marketplace suite to see what breaks**

Run: `packages/marketplace/vendor/bin/pest` (from `packages/marketplace/`)
Expected: FAIL — `SecurityScanner` still reads `$advisory->fixedIn` (undefined property). This is expected; Task 3 fixes the scanner. The Advisory constructions in tests are positional (`new Advisory('GHSA-1', 'critical', 'RCE', '>=1.0.1')`), so they still compile — only the scanner's `->fixedIn` read is now broken.

- [ ] **Step 4: Commit (the scanner is intentionally red until Task 3)**

To keep the tree compiling for the class itself, commit the rename now; Task 3's commit makes the suite green again. (This is a deliberately small, reviewable step — the two commits land together on the branch.)

```bash
git add packages/marketplace/src/Contracts/Advisory.php
git commit --no-verify --signoff -m "refactor(marketplace): rename Advisory::fixedIn to affectedVersions

The field is a Composer constraint of the versions an advisory AFFECTS, not
a single fixed version — the scanner will match the plugin's installed
version against it. Value-object rename only; scanner read updated next.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

Note to implementer: do NOT run `composer run analyse` between Task 2 and Task 3 — the scanner's stale `->fixedIn` read will fail PHPStan until Task 3. Proceed straight to Task 3.

---

### Task 3: Filter findings by version in `SecurityScanner`

Wire `VersionMatcher` into `lookupVulnerabilities` so only genuinely-affected plugins produce findings. This is where the bug is actually fixed and proven.

**Files:**
- Modify: `packages/marketplace/src/Services/SecurityScanner.php:105-133` (`lookupVulnerabilities`, both composer + npm branches)
- Test: `packages/marketplace/tests/Unit/Services/SecurityScannerTest.php` (add 3 tests)

**Interfaces:**
- Consumes: `VersionMatcher::isAffected(?string, string): bool` (Task 1); `Advisory::$affectedVersions` (Task 2); `Plugin::$latest_version`.

- [ ] **Step 1: Write the failing tests**

Add to `packages/marketplace/tests/Unit/Services/SecurityScannerTest.php` (the file already has `makeScanPlugin()` and a `FakeVulnerabilityDatabase`; reuse them):

```php
it('does not flag a plugin whose version is above the affected range', function (): void {
    Event::fake([PluginAutoDelistedEvent::class]);

    $vulnDb = new FakeVulnerabilityDatabase([
        'composer:acme/fixed' => [
            new Advisory('GHSA-1', 'critical', 'RCE in old versions', '<2.0'),
        ],
    ]);
    $scanner = new SecurityScanner($vulnDb);

    $plugin = makeScanPlugin([
        'composer_package' => 'acme/fixed',
        'latest_version' => '2.5.0', // already patched — above the <2.0 range
    ]);

    $scan = $scanner->scan($plugin);

    expect($scan->status)->toBe('passed');
    expect($scan->severity)->toBeNull();
    expect($scan->findings)->toBe([]);

    $plugin->refresh();
    expect($plugin->status)->toBe('published'); // NOT archived
    Event::assertNotDispatched(PluginAutoDelistedEvent::class);
});

it('flags a plugin whose version is inside the affected range', function (): void {
    $vulnDb = new FakeVulnerabilityDatabase([
        'composer:acme/vuln' => [
            new Advisory('GHSA-2', 'critical', 'RCE', '>=1.0,<1.5'),
        ],
    ]);
    $scanner = new SecurityScanner($vulnDb);

    $plugin = makeScanPlugin([
        'composer_package' => 'acme/vuln',
        'latest_version' => '1.3.0', // inside the range
    ]);

    $scan = $scanner->scan($plugin);

    expect($scan->status)->toBe('failed');
    expect($scan->severity)->toBe('critical');
});

it('fails safe: flags when the installed version is unknown', function (): void {
    $vulnDb = new FakeVulnerabilityDatabase([
        'composer:acme/noversion' => [
            new Advisory('GHSA-3', 'high', 'serious', '<2.0'),
        ],
    ]);
    $scanner = new SecurityScanner($vulnDb);

    $plugin = makeScanPlugin([
        'composer_package' => 'acme/noversion',
        // latest_version not set → null → fail-safe affected
    ]);

    $scan = $scanner->scan($plugin);

    expect($scan->severity)->toBe('high');
    expect($scan->findings)->toHaveCount(1);
});
```

- [ ] **Step 2: Run to verify the first two fail (and prove the bug)**

Run: `packages/marketplace/vendor/bin/pest tests/Unit/Services/SecurityScannerTest.php` (from `packages/marketplace/`)
Expected: FAIL on "does not flag a plugin whose version is above the affected range" — currently the scanner flags it (the bug). Also the whole file errors on the stale `->fixedIn` read from Task 2. Both are fixed in Step 3.

- [ ] **Step 3: Wire `VersionMatcher` into `lookupVulnerabilities`**

In `packages/marketplace/src/Services/SecurityScanner.php`, add the import at the top:

```php
use Arqel\Marketplace\Services\VersionMatcher;
```
(Same namespace, so the `use` is optional — you may reference `VersionMatcher` directly. Prefer no import since it's the same namespace; if you add one, PHPStan/Pint may flag a redundant import.)

Replace both foreach bodies so each skips advisories the plugin's version isn't affected by, and reads the renamed field. Composer branch:

```php
if (is_string($plugin->composer_package) && $plugin->composer_package !== '') {
    foreach ($this->vulnDb->lookup($plugin->composer_package, 'composer') as $advisory) {
        if (! VersionMatcher::isAffected($plugin->latest_version, $advisory->affectedVersions)) {
            continue;
        }

        $findings[] = [
            'type' => 'vulnerability',
            'severity' => $advisory->severity,
            'advisory_id' => $advisory->id,
            'summary' => $advisory->summary,
            'package' => $plugin->composer_package,
            'ecosystem' => 'composer',
        ];
    }
}
```

npm branch (identical shape):

```php
if (is_string($plugin->npm_package) && $plugin->npm_package !== '') {
    foreach ($this->vulnDb->lookup($plugin->npm_package, 'npm') as $advisory) {
        if (! VersionMatcher::isAffected($plugin->latest_version, $advisory->affectedVersions)) {
            continue;
        }

        $findings[] = [
            'type' => 'vulnerability',
            'severity' => $advisory->severity,
            'advisory_id' => $advisory->id,
            'summary' => $advisory->summary,
            'package' => $plugin->npm_package,
            'ecosystem' => 'npm',
        ];
    }
}
```

Note: `$plugin->latest_version` may be `null` (nullable column) — that's intended; `VersionMatcher::isAffected(null, ...)` returns `true` (fail-safe).

- [ ] **Step 4: Run the full marketplace suite**

Run: `packages/marketplace/vendor/bin/pest` (from `packages/marketplace/`)
Expected: PASS — the 3 new tests pass, and every pre-existing SecurityScanner test still passes (they use `makeScanPlugin` with `latest_version` null → fail-safe affected → unchanged behavior).

- [ ] **Step 5: Lint + static analysis from repo root**

Run (from repo root):
```bash
composer run lint
composer run analyse
```
Expected: both clean. If Pint reports files, run `composer run format`, re-run the suite, and include the formatting in this commit.

- [ ] **Step 6: Commit**

```bash
git add packages/marketplace/src/Services/SecurityScanner.php packages/marketplace/tests/Unit/Services/SecurityScannerTest.php
git commit --no-verify --signoff -m "fix(marketplace): match plugin version against advisory range before flagging

SecurityScanner flagged (and auto-delisted) plugins for advisories that only
affect older versions, because it ignored Advisory::affectedVersions. Now
lookupVulnerabilities skips advisories the installed version is not affected
by (VersionMatcher). Fail-safe: unknown version still flags. Closes the
false-positive auto-delist bug.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:**
- Rename `fixedIn`→`affectedVersions` → Task 2. ✅
- `VersionMatcher` + 3 fail-safes (null/empty version, empty constraint, parse failure) → Task 1 (tests cover all). ✅
- Catch `UnexpectedValueException | InvalidArgumentException` (not Throwable) → Task 1 Step 4. ✅
- Filter in `lookupVulnerabilities`, both branches → Task 3. ✅
- `composer/semver` explicit require `^3.0` → Task 1 Step 1. ✅
- Contract `VulnerabilityDatabase::lookup` / rollup / statusFor / auto-delist unchanged → none of the tasks touch them. ✅
- TDD proof: "plugin 2.5.0 vs `<2.0` → passed" fails baseline → Task 3 Step 1/2. ✅
- Test of second exception class (stability) → Task 1 Step 2 (`'>=1.0.0@badstability'`). ✅

**2. Placeholder scan:** every code step has complete code; no TBD/TODO. The one implementer note (no `analyse` between Task 2 and 3) is a concrete sequencing instruction, not a placeholder. ✅

**3. Type consistency:** `VersionMatcher::isAffected(?string, string): bool`, `Advisory::$affectedVersions`, `Plugin::$latest_version` — consistent across Tasks 1–3. ✅

**Cross-task red state note:** Task 2 deliberately leaves the scanner's `->fixedIn` read broken (suite red) until Task 3. This is called out in Task 2 Step 3/4 and Task 3. A task reviewer seeing Task 2 in isolation should know the red suite is expected and resolved by Task 3 — flagged here so it isn't mistaken for an incomplete task. If a reviewer prefers no red intermediate state, Tasks 2 and 3 can be executed and committed as a pair.

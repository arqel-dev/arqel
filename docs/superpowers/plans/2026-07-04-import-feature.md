# arqel/import (Imports CSV/Excel) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a first-class Imports package (`arqel-dev/import`) that reads CSV/XLSX files, validates rows against declared columns, persists valid rows via a queued job, and collects invalid rows into a downloadable failed-rows CSV — closing competitive gap #1 vs Filament/Nova.

**Architecture:** Mirror `packages/export`. A `FileReader` interface (CsvReader/XlsxReader over `spatie/simple-excel`) streams rows lazily. A dev-authored `Importer` subclass declares `ImportColumn`s and `resolveRecord()`. `ProcessImportJob` chunks the stream, validates each row, persists valid ones in a per-chunk DB transaction, and writes failed rows to a CSV. `ImportAction extends Action` for panel wiring; two authorized controllers handle upload + failed-rows download.

**Tech Stack:** PHP 8.3+, Laravel 12/13, `spatie/simple-excel` ^3.0, `spatie/laravel-package-tools`, Pest 3, Orchestra Testbench 10, Larastan.

## Global Constraints

- PHP `^8.3`; `illuminate/support` `^12.0|^13.0`. (verbatim from export composer.json)
- `declare(strict_types=1);` in every PHP file. (CLAUDE.md rule 6)
- Classes `final` unless extensibility is design intent — `Importer` base class is NOT final (dev extends it); everything else IS final. (CLAUDE.md rule 5)
- Package namespace `Arqel\Import\`; tests namespace `Arqel\Import\Tests\`.
- Dependency `spatie/simple-excel:^3.0` — already used by export, NOT a new ecosystem dep.
- Coverage target ≥90% (core PHP, PLANNING/12 §2.2 / ADR-008).
- Commits: Conventional Commits + `--signoff` + `--no-verify` (Husky may be absent in worktree). Scope `import`. Body references this plan.
- **Host gotcha:** no `ext-zip` → XLSX tests fail locally (baseline, NOT a regression). Run `composer install --ignore-platform-req=ext-zip`. XLSX correctness is trusted on CI. CSV tests run fine locally.
- **Tooling:** `pint` runs locally from root `vendor/bin/pint <files> --test` — run on BOTH src AND test files before each commit. PHPStan does NOT run locally (testbench symlink loop) — trust CI.
- Copy: PT-BR in docs/SKILL/README; English in code. i18n keys like `arqel::import.*` for user-facing labels (mirror `arqel::actions.export`).

---

### Task 1: Package scaffold (composer.json + ServiceProvider + FileReader contract)

Sets up the installable package skeleton so every later task has a home. Deliverable: `composer install` succeeds and the provider auto-discovers.

**Files:**
- Create: `packages/import/composer.json`
- Create: `packages/import/src/ImportServiceProvider.php`
- Create: `packages/import/src/Contracts/FileReader.php`
- Create: `packages/import/phpunit.xml` (copy from `packages/export/phpunit.xml`)
- Create: `packages/import/tests/Pest.php`, `packages/import/tests/TestCase.php` (copy from export, rename namespace)
- Test: `packages/import/tests/Unit/Contracts/FileReaderContractTest.php`

**Interfaces:**
- Produces: `interface Arqel\Import\Contracts\FileReader { public function read(string $source): iterable; }`
- Produces: `Arqel\Import\ImportServiceProvider` (PackageServiceProvider, name `arqel-import`)

- [ ] **Step 1: Copy the export composer.json as the base and adapt**

Create `packages/import/composer.json` (adapted from `packages/export/composer.json`):

```json
{
  "name": "arqel-dev/import",
  "version": "0.15.1",
  "description": "Import pipeline for Arqel — CSV/XLSX readers, per-row validation and the ImportAction.",
  "type": "library",
  "license": "MIT",
  "keywords": ["laravel", "arqel", "import", "csv", "xlsx"],
  "homepage": "https://arqel.dev",
  "support": {
    "issues": "https://github.com/arqel-dev/arqel/issues",
    "source": "https://github.com/arqel-dev/arqel",
    "docs": "https://arqel.dev/docs"
  },
  "require": {
    "php": "^8.3",
    "arqel-dev/actions": "@dev",
    "arqel-dev/core": "@dev",
    "illuminate/support": "^12.0|^13.0",
    "spatie/laravel-package-tools": "^1.16",
    "spatie/simple-excel": "^3.0"
  },
  "require-dev": {
    "larastan/larastan": "^3.9",
    "laravel/pint": "^1.29",
    "orchestra/testbench": "^10.0",
    "pestphp/pest": "^3.0",
    "pestphp/pest-plugin-laravel": "^3.0"
  },
  "autoload": { "psr-4": { "Arqel\\Import\\": "src/" } },
  "autoload-dev": { "psr-4": { "Arqel\\Import\\Tests\\": "tests/" } },
  "config": {
    "sort-packages": true,
    "allow-plugins": { "pestphp/pest-plugin": true }
  },
  "extra": { "laravel": { "providers": ["Arqel\\Import\\ImportServiceProvider"] } },
  "repositories": [
    { "type": "path", "url": "../core", "options": { "symlink": true } },
    { "type": "path", "url": "../actions", "options": { "symlink": true } },
    { "type": "path", "url": "../fields", "options": { "symlink": true } },
    { "type": "path", "url": "../form", "options": { "symlink": true } }
  ],
  "scripts": {
    "test": "vendor/bin/pest",
    "test:coverage": "vendor/bin/pest --coverage --min=90",
    "analyse": "phpstan analyse",
    "lint": "pint --test",
    "format": "pint"
  },
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

- [ ] **Step 2: Write the FileReader contract**

Create `packages/import/src/Contracts/FileReader.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Contracts;

/**
 * Format-agnostic file reader contract.
 *
 * Implementations stream rows lazily from a file, yielding one
 * associative array per data row keyed by header. They never load
 * the whole file into memory.
 *
 *   - `CsvReader`  → Task 3 (spatie/simple-excel)
 *   - `XlsxReader` → Task 4 (spatie/simple-excel)
 */
interface FileReader
{
    /**
     * @param string $source Absolute path of the file to read.
     *
     * @return iterable<int, array<string, string|null>> Rows keyed by header.
     */
    public function read(string $source): iterable;
}
```

- [ ] **Step 3: Write the ServiceProvider**

Create `packages/import/src/ImportServiceProvider.php` (mirrors ExportServiceProvider; logger binding lands in Task 8, route in Task 10 — for now name only):

```php
<?php

declare(strict_types=1);

namespace Arqel\Import;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Auto-discovered provider for `arqel-dev/import`.
 */
final class ImportServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('arqel-import');
    }
}
```

- [ ] **Step 4: Copy test harness from export**

Copy `packages/export/tests/TestCase.php` and `packages/export/tests/Pest.php` to `packages/import/tests/`, replacing `Arqel\Export` → `Arqel\Import` and `ExportServiceProvider` → `ImportServiceProvider` in the `getPackageProviders()` array. Copy `packages/export/phpunit.xml` verbatim to `packages/import/phpunit.xml`.

- [ ] **Step 5: Write the failing contract test**

Create `packages/import/tests/Unit/Contracts/FileReaderContractTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\Contracts\FileReader;

it('declares a read method returning iterable', function (): void {
    $reflection = new ReflectionMethod(FileReader::class, 'read');

    expect($reflection->getReturnType()?->getName())->toBe('iterable')
        ->and($reflection->getNumberOfParameters())->toBe(1);
});
```

- [ ] **Step 6: Install and run**

Run: `cd packages/import && composer install --ignore-platform-req=ext-zip && vendor/bin/pest --no-coverage`
Expected: PASS (1 test).

- [ ] **Step 7: Lint + commit**

Run: `/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src packages/import/tests --test`
Then:
```bash
git add packages/import/composer.json packages/import/src packages/import/tests packages/import/phpunit.xml
git commit --no-verify --signoff -m "feat(import): scaffold arqel/import package + FileReader contract

Implements Task 1 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: ImportFormat enum

**Files:**
- Create: `packages/import/src/ImportFormat.php`
- Test: `packages/import/tests/Unit/ImportFormatTest.php`

**Interfaces:**
- Produces: `enum Arqel\Import\ImportFormat: string { case CSV = 'csv'; case XLSX = 'xlsx'; public function extension(): string; public static function fromExtension(string $ext): self; }`

- [ ] **Step 1: Write the failing test**

Create `packages/import/tests/Unit/ImportFormatTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\ImportFormat;

it('maps cases to extensions', function (): void {
    expect(ImportFormat::CSV->extension())->toBe('csv')
        ->and(ImportFormat::XLSX->extension())->toBe('xlsx');
});

it('resolves a format from a file extension case-insensitively', function (): void {
    expect(ImportFormat::fromExtension('CSV'))->toBe(ImportFormat::CSV)
        ->and(ImportFormat::fromExtension('xlsx'))->toBe(ImportFormat::XLSX);
});

it('throws on an unsupported extension', function (): void {
    ImportFormat::fromExtension('pdf');
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd packages/import && vendor/bin/pest tests/Unit/ImportFormatTest.php --no-coverage`
Expected: FAIL ("Class ImportFormat not found").

- [ ] **Step 3: Implement**

Create `packages/import/src/ImportFormat.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import;

use InvalidArgumentException;

enum ImportFormat: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';

    public function extension(): string
    {
        return $this->value;
    }

    public static function fromExtension(string $ext): self
    {
        return self::tryFrom(strtolower($ext))
            ?? throw new InvalidArgumentException("Unsupported import format [{$ext}].");
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `cd packages/import && vendor/bin/pest tests/Unit/ImportFormatTest.php --no-coverage`
Expected: PASS (3 tests).

- [ ] **Step 5: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src/ImportFormat.php packages/import/tests/Unit/ImportFormatTest.php --test
git add packages/import/src/ImportFormat.php packages/import/tests/Unit/ImportFormatTest.php
git commit --no-verify --signoff -m "feat(import): add ImportFormat enum (CSV/XLSX)

Implements Task 2 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: CsvReader

**Files:**
- Create: `packages/import/src/Readers/CsvReader.php`
- Test: `packages/import/tests/Unit/Readers/CsvReaderTest.php`
- Test fixture: `packages/import/tests/Fixtures/users-valid.csv`

**Interfaces:**
- Consumes: `Arqel\Import\Contracts\FileReader` (Task 1)
- Produces: `final class Arqel\Import\Readers\CsvReader implements FileReader` — `read(string $source): iterable` yields `array<string, string|null>` per row keyed by header, lazily (a `Generator`/`LazyCollection`).

- [ ] **Step 1: Create the fixture**

Create `packages/import/tests/Fixtures/users-valid.csv`:

```
name,email
Ada Lovelace,ada@example.com
Alan Turing,alan@example.com
```

- [ ] **Step 2: Write the failing test**

Create `packages/import/tests/Unit/Readers/CsvReaderTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\Readers\CsvReader;

it('yields one associative array per data row keyed by header', function (): void {
    $rows = iterator_to_array((function () {
        yield from (new CsvReader)->read(__DIR__ . '/../../Fixtures/users-valid.csv');
    })());

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
        ->and($rows[1]['email'])->toBe('alan@example.com');
});

it('reads lazily (returns a Generator, not a materialised array)', function (): void {
    $result = (new CsvReader)->read(__DIR__ . '/../../Fixtures/users-valid.csv');

    expect($result)->toBeInstanceOf(Traversable::class);
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `cd packages/import && vendor/bin/pest tests/Unit/Readers/CsvReaderTest.php --no-coverage`
Expected: FAIL ("Class CsvReader not found").

- [ ] **Step 4: Implement**

Create `packages/import/src/Readers/CsvReader.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Readers;

use Arqel\Import\Contracts\FileReader;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Streaming CSV reader backed by `spatie/simple-excel`.
 *
 * Yields one associative array per data row, keyed by the file's
 * header row. Reading is lazy — the whole file is never held in memory.
 */
final class CsvReader implements FileReader
{
    public function read(string $source): iterable
    {
        foreach (SimpleExcelReader::create($source, 'csv')->getRows() as $row) {
            /** @var array<string, string|null> $row */
            yield $row;
        }
    }
}
```

- [ ] **Step 5: Run to verify pass**

Run: `cd packages/import && vendor/bin/pest tests/Unit/Readers/CsvReaderTest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 6: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src/Readers/CsvReader.php packages/import/tests/Unit/Readers/CsvReaderTest.php --test
git add packages/import/src/Readers/CsvReader.php packages/import/tests/Unit/Readers/CsvReaderTest.php packages/import/tests/Fixtures/users-valid.csv
git commit --no-verify --signoff -m "feat(import): add streaming CsvReader

Implements Task 3 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: XlsxReader

**Files:**
- Create: `packages/import/src/Readers/XlsxReader.php`
- Test: `packages/import/tests/Unit/Readers/XlsxReaderTest.php`

**Interfaces:**
- Consumes: `Arqel\Import\Contracts\FileReader` (Task 1)
- Produces: `final class Arqel\Import\Readers\XlsxReader implements FileReader` — same contract as CsvReader.

- [ ] **Step 1: Write the failing test (skips when ext-zip absent)**

Create `packages/import/tests/Unit/Readers/XlsxReaderTest.php`. The test generates a tiny xlsx via `SimpleExcelWriter` and reads it back. It skips when `ext-zip` is unavailable (host baseline), so it only truly runs on CI:

```php
<?php

declare(strict_types=1);

use Arqel\Import\Readers\XlsxReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

beforeEach(function (): void {
    if (! extension_loaded('zip')) {
        $this->markTestSkipped('ext-zip not available (host baseline); XLSX verified on CI.');
    }
});

it('yields one associative array per data row keyed by header', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
    SimpleExcelWriter::create($path)
        ->addRow(['name' => 'Ada Lovelace', 'email' => 'ada@example.com'])
        ->addRow(['name' => 'Alan Turing', 'email' => 'alan@example.com'])
        ->close();

    $rows = iterator_to_array((function () use ($path) {
        yield from (new XlsxReader)->read($path);
    })());

    expect($rows)->toHaveCount(2)
        ->and($rows[0])->toBe(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    @unlink($path);
});
```

- [ ] **Step 2: Run to verify it fails (or skips locally)**

Run: `cd packages/import && vendor/bin/pest tests/Unit/Readers/XlsxReaderTest.php --no-coverage`
Expected locally: SKIPPED (no ext-zip). On CI: FAIL ("Class XlsxReader not found") before implementing.

- [ ] **Step 3: Implement**

Create `packages/import/src/Readers/XlsxReader.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Readers;

use Arqel\Import\Contracts\FileReader;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Streaming XLSX reader backed by `spatie/simple-excel` (requires ext-zip).
 */
final class XlsxReader implements FileReader
{
    public function read(string $source): iterable
    {
        foreach (SimpleExcelReader::create($source, 'xlsx')->getRows() as $row) {
            /** @var array<string, string|null> $row */
            yield $row;
        }
    }
}
```

- [ ] **Step 4: Run to verify pass (or skip locally)**

Run: `cd packages/import && vendor/bin/pest tests/Unit/Readers/XlsxReaderTest.php --no-coverage`
Expected locally: SKIPPED. On CI: PASS.

- [ ] **Step 5: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src/Readers/XlsxReader.php packages/import/tests/Unit/Readers/XlsxReaderTest.php --test
git add packages/import/src/Readers/XlsxReader.php packages/import/tests/Unit/Readers/XlsxReaderTest.php
git commit --no-verify --signoff -m "feat(import): add streaming XlsxReader

Implements Task 4 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: ImportColumn

**Files:**
- Create: `packages/import/src/ImportColumn.php`
- Test: `packages/import/tests/Unit/ImportColumnTest.php`

**Interfaces:**
- Produces: `final class Arqel\Import\ImportColumn` with:
  - `public static function make(string $name): self` — `$name` = expected header.
  - `public function label(string $label): self`
  - `public function rules(array $rules): self`
  - `public function fillUsing(Closure $callback): self` — transforms the raw cell value.
  - `public function requiredMapping(bool $required = true): self`
  - Getters: `getName(): string`, `getLabel(): string` (falls back to `$name`), `getRules(): array`, `isMappingRequired(): bool`, `applyFill(string|null $raw): mixed` (runs `fillUsing` or returns raw).

- [ ] **Step 1: Write the failing test**

Create `packages/import/tests/Unit/ImportColumnTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\ImportColumn;

it('exposes name and defaults label to the name', function (): void {
    $col = ImportColumn::make('email');

    expect($col->getName())->toBe('email')
        ->and($col->getLabel())->toBe('email')
        ->and($col->getRules())->toBe([])
        ->and($col->isMappingRequired())->toBeFalse();
});

it('is fluent for label, rules, required mapping', function (): void {
    $col = ImportColumn::make('email')
        ->label('E-mail')
        ->rules(['required', 'email'])
        ->requiredMapping();

    expect($col->getLabel())->toBe('E-mail')
        ->and($col->getRules())->toBe(['required', 'email'])
        ->and($col->isMappingRequired())->toBeTrue();
});

it('applies fillUsing to transform the raw value, else returns raw', function (): void {
    $transforming = ImportColumn::make('email')->fillUsing(fn (string $v) => strtolower(trim($v)));
    $plain = ImportColumn::make('name');

    expect($transforming->applyFill('  ADA@Example.com '))->toBe('ada@example.com')
        ->and($plain->applyFill('Ada'))->toBe('Ada');
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd packages/import && vendor/bin/pest tests/Unit/ImportColumnTest.php --no-coverage`
Expected: FAIL ("Class ImportColumn not found").

- [ ] **Step 3: Implement**

Create `packages/import/src/ImportColumn.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import;

use Closure;

/**
 * Declarative column descriptor for an {@see Importer}.
 *
 * `make('email')` matches the `email` header in the source file. The
 * optional `fillUsing` closure transforms the raw cell value before
 * validation; `rules` are Laravel validation rules applied per row;
 * `requiredMapping` marks the header as mandatory (a missing header is
 * a setup error, not a per-row error).
 */
final class ImportColumn
{
    private string $label;

    /** @var array<int, mixed> */
    private array $rules = [];

    private ?Closure $fillUsing = null;

    private bool $mappingRequired = false;

    private function __construct(private readonly string $name)
    {
        $this->label = $name;
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** @param array<int, mixed> $rules */
    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function fillUsing(Closure $callback): self
    {
        $this->fillUsing = $callback;

        return $this;
    }

    public function requiredMapping(bool $required = true): self
    {
        $this->mappingRequired = $required;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /** @return array<int, mixed> */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function isMappingRequired(): bool
    {
        return $this->mappingRequired;
    }

    public function applyFill(string|null $raw): mixed
    {
        if ($this->fillUsing === null) {
            return $raw;
        }

        return ($this->fillUsing)($raw);
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `cd packages/import && vendor/bin/pest tests/Unit/ImportColumnTest.php --no-coverage`
Expected: PASS (3 tests).

- [ ] **Step 5: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src/ImportColumn.php packages/import/tests/Unit/ImportColumnTest.php --test
git add packages/import/src/ImportColumn.php packages/import/tests/Unit/ImportColumnTest.php
git commit --no-verify --signoff -m "feat(import): add ImportColumn declarative descriptor

Implements Task 5 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: Importer base class

**Files:**
- Create: `packages/import/src/Importer.php`
- Test: `packages/import/tests/Unit/ImporterTest.php`
- Test fixture: `packages/import/tests/Fixtures/Importers/StubUserImporter.php`, `packages/import/tests/Fixtures/Models/ImportUser.php`

**Interfaces:**
- Consumes: `Arqel\Import\ImportColumn` (Task 5)
- Produces: `abstract class Arqel\Import\Importer` with:
  - `public static string $model` (child sets it)
  - `abstract public function columns(): array` (returns `ImportColumn[]`)
  - `public function resolveRecord(array $data): Model` — default `new static::$model`
  - `public function rules(): array` — derived from columns: `[name => rules]` (used by the job)

- [ ] **Step 1: Create fixtures**

Create `packages/import/tests/Fixtures/Models/ImportUser.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class ImportUser extends Model
{
    protected $table = 'import_users';

    protected $guarded = [];

    public $timestamps = false;
}
```

Create `packages/import/tests/Fixtures/Importers/StubUserImporter.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Tests\Fixtures\Importers;

use Arqel\Import\ImportColumn;
use Arqel\Import\Importer;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;

final class StubUserImporter extends Importer
{
    public static string $model = ImportUser::class;

    public function columns(): array
    {
        return [
            ImportColumn::make('name')->rules(['required', 'string']),
            ImportColumn::make('email')->rules(['required', 'email']),
        ];
    }
}
```

- [ ] **Step 2: Write the failing test**

Create `packages/import/tests/Unit/ImporterTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;

it('derives a rules map keyed by column name', function (): void {
    $rules = (new StubUserImporter)->rules();

    expect($rules)->toBe([
        'name' => ['required', 'string'],
        'email' => ['required', 'email'],
    ]);
});

it('resolves a fresh model instance by default', function (): void {
    $record = (new StubUserImporter)->resolveRecord(['name' => 'Ada', 'email' => 'ada@example.com']);

    expect($record)->toBeInstanceOf(ImportUser::class)
        ->and($record->exists)->toBeFalse();
});
```

- [ ] **Step 3: Run to verify it fails**

Run: `cd packages/import && vendor/bin/pest tests/Unit/ImporterTest.php --no-coverage`
Expected: FAIL ("Class Importer not found").

- [ ] **Step 4: Implement**

Create `packages/import/src/Importer.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * Base class a consumer app extends to declare an import.
 *
 * The child sets `$model`, declares `columns()`, and optionally
 * overrides `resolveRecord()` to upsert instead of insert.
 */
abstract class Importer
{
    /** @var class-string<Model> */
    public static string $model;

    /** @return array<int, ImportColumn> */
    abstract public function columns(): array;

    /**
     * Resolve the Eloquent model a validated row maps to.
     * Default: a fresh instance (insert). Override for upserts, e.g.
     * `return User::firstOrNew(['email' => $data['email']]);`.
     *
     * @param array<string, mixed> $data
     */
    public function resolveRecord(array $data): Model
    {
        $model = static::$model;

        return new $model;
    }

    /**
     * Validation rules keyed by column name, derived from columns().
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->columns() as $column) {
            $rules[$column->getName()] = $column->getRules();
        }

        return $rules;
    }
}
```

- [ ] **Step 5: Run to verify pass**

Run: `cd packages/import && vendor/bin/pest tests/Unit/ImporterTest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 6: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src/Importer.php packages/import/tests --test
git add packages/import/src/Importer.php packages/import/tests/Unit/ImporterTest.php packages/import/tests/Fixtures
git commit --no-verify --signoff -m "feat(import): add Importer base class (columns/rules/resolveRecord)

Implements Task 6 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: ImportLogger contract + NullImportLogger

**Files:**
- Create: `packages/import/src/Contracts/ImportLogger.php`
- Create: `packages/import/src/Logging/NullImportLogger.php`
- Modify: `packages/import/src/ImportServiceProvider.php` (bind `singletonIf`)
- Test: `packages/import/tests/Unit/Logging/NullImportLoggerTest.php`

**Interfaces:**
- Produces: `interface Arqel\Import\Contracts\ImportLogger` with:
  - `logQueued(string $importId, ImportFormat $format): void`
  - `progress(string $importId, int $imported, int $skipped): void`
  - `logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath): void`
  - `logFailed(string $importId, ImportFormat $format, Throwable $exception): void`
- Produces: `final class Arqel\Import\Logging\NullImportLogger implements ImportLogger` (no-op)

- [ ] **Step 1: Write the failing test**

Create `packages/import/tests/Unit/Logging/NullImportLoggerTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\ImportFormat;
use Arqel\Import\Logging\NullImportLogger;

it('is bound as the default ImportLogger', function (): void {
    expect(app(ImportLogger::class))->toBeInstanceOf(NullImportLogger::class);
});

it('accepts all lifecycle calls as no-ops', function (): void {
    $logger = new NullImportLogger;
    $logger->logQueued('id', ImportFormat::CSV);
    $logger->progress('id', 5, 1);
    $logger->logCompleted('id', 5, 1, '/tmp/failed.csv');
    $logger->logFailed('id', ImportFormat::CSV, new RuntimeException('x'));

    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd packages/import && vendor/bin/pest tests/Unit/Logging/NullImportLoggerTest.php --no-coverage`
Expected: FAIL ("Interface ImportLogger not found" / binding missing).

- [ ] **Step 3: Implement contract + null impl**

Create `packages/import/src/Contracts/ImportLogger.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Contracts;

use Arqel\Import\ImportFormat;
use Throwable;

/**
 * Lifecycle + progress hook for import jobs.
 *
 * Default is {@see \Arqel\Import\Logging\NullImportLogger} (no-op).
 * Apps persist an `imports` row and/or notify users by binding their
 * own implementation in a service provider.
 */
interface ImportLogger
{
    public function logQueued(string $importId, ImportFormat $format): void;

    public function progress(string $importId, int $imported, int $skipped): void;

    public function logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath): void;

    public function logFailed(string $importId, ImportFormat $format, Throwable $exception): void;
}
```

Create `packages/import/src/Logging/NullImportLogger.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Logging;

use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\ImportFormat;
use Throwable;

final class NullImportLogger implements ImportLogger
{
    public function logQueued(string $importId, ImportFormat $format): void
    {
        // no-op
    }

    public function progress(string $importId, int $imported, int $skipped): void
    {
        // no-op
    }

    public function logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath): void
    {
        // no-op
    }

    public function logFailed(string $importId, ImportFormat $format, Throwable $exception): void
    {
        // no-op
    }
}
```

- [ ] **Step 4: Bind in the ServiceProvider**

Modify `packages/import/src/ImportServiceProvider.php` — add `packageRegistered()` and imports:

```php
use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\Logging\NullImportLogger;
```

Add the method to the class body:

```php
    public function packageRegistered(): void
    {
        $this->app->singletonIf(ImportLogger::class, NullImportLogger::class);
    }
```

- [ ] **Step 5: Run to verify pass**

Run: `cd packages/import && vendor/bin/pest tests/Unit/Logging/NullImportLoggerTest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 6: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src packages/import/tests --test
git add packages/import/src/Contracts/ImportLogger.php packages/import/src/Logging/NullImportLogger.php packages/import/src/ImportServiceProvider.php packages/import/tests/Unit/Logging
git commit --no-verify --signoff -m "feat(import): add ImportLogger contract + NullImportLogger default

Implements Task 7 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: ProcessImportJob (the core)

**Files:**
- Create: `packages/import/src/Jobs/ProcessImportJob.php`
- Test: `packages/import/tests/Feature/ProcessImportJobTest.php`
- Test fixtures: `packages/import/tests/Fixtures/users-mixed.csv`, and a migration for the `import_users` table in the TestCase.

**Interfaces:**
- Consumes: `FileReader` (CsvReader/XlsxReader), `ImportFormat`, `Importer`, `ImportColumn`, `ImportLogger` (Tasks 1-7)
- Produces: `final class Arqel\Import\Jobs\ProcessImportJob implements ShouldQueue`. Constructor: `(string $importId, ImportFormat $format, class-string<Importer> $importerClass, string $sourcePath, ?string $failedRowsDir = null)`. `handle(ImportLogger $logger): void`.

- [ ] **Step 1: Add the import_users migration to TestCase**

In `packages/import/tests/TestCase.php`, add a `defineDatabaseMigrations()` or `getEnvironmentSetUp()` schema for `import_users` (id, name, email). Follow the pattern in `packages/export/tests/TestCase.php` if it defines tables; otherwise add:

```php
protected function defineDatabaseMigrations(): void
{
    \Illuminate\Support\Facades\Schema::create('import_users', function (\Illuminate\Database\Schema\Blueprint $table): void {
        $table->increments('id');
        $table->string('name');
        $table->string('email');
    });
}
```

- [ ] **Step 2: Create the mixed fixture**

Create `packages/import/tests/Fixtures/users-mixed.csv` (row 2 valid, row 3 invalid email, row 4 valid):

```
name,email
Ada Lovelace,ada@example.com
Bad Row,not-an-email
Alan Turing,alan@example.com
```

- [ ] **Step 3: Write the failing feature test**

Create `packages/import/tests/Feature/ProcessImportJobTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\ImportFormat;
use Arqel\Import\Jobs\ProcessImportJob;
use Arqel\Import\Logging\NullImportLogger;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Arqel\Import\Tests\Fixtures\Models\ImportUser;

it('imports valid rows and skips invalid ones into a failed-rows CSV', function (): void {
    $dir = sys_get_temp_dir() . '/imp-' . uniqid();
    $job = new ProcessImportJob(
        importId: 'test-import-1',
        format: ImportFormat::CSV,
        importerClass: StubUserImporter::class,
        sourcePath: __DIR__ . '/../Fixtures/users-mixed.csv',
        failedRowsDir: $dir,
    );

    $job->handle(new NullImportLogger);

    // 2 valid rows persisted, 1 invalid skipped
    expect(ImportUser::count())->toBe(2);
    expect(ImportUser::where('email', 'ada@example.com')->exists())->toBeTrue();
    expect(ImportUser::where('email', 'not-an-email')->exists())->toBeFalse();

    // failed-rows CSV written with the bad row + an _errors column
    $failed = $dir . '/failed-test-import-1.csv';
    expect(file_exists($failed))->toBeTrue();
    $contents = file_get_contents($failed);
    expect($contents)->toContain('not-an-email')
        ->and($contents)->toContain('_errors');
});

it('reports progress and completion counts to the logger', function (): void {
    // Implement the interface directly — NullImportLogger is `final` (Task 7)
    // and cannot be extended. logQueued/logFailed are irrelevant here, so they
    // are no-ops.
    $spy = new class implements ImportLogger {
        public array $progressCalls = [];
        public array $completed = [];
        public function logQueued(string $importId, ImportFormat $format): void {}
        public function progress(string $importId, int $imported, int $skipped): void
        {
            $this->progressCalls[] = [$imported, $skipped];
        }
        public function logCompleted(string $importId, int $imported, int $skipped, ?string $failedRowsPath): void
        {
            $this->completed = [$imported, $skipped, $failedRowsPath];
        }
        public function logFailed(string $importId, ImportFormat $format, Throwable $exception): void {}
    };

    (new ProcessImportJob(
        importId: 'test-import-2',
        format: ImportFormat::CSV,
        importerClass: StubUserImporter::class,
        sourcePath: __DIR__ . '/../Fixtures/users-mixed.csv',
        failedRowsDir: sys_get_temp_dir() . '/imp-' . uniqid(),
    ))->handle($spy);

    expect($spy->completed[0])->toBe(2)   // imported
        ->and($spy->completed[1])->toBe(1) // skipped
        ->and($spy->completed[2])->not->toBeNull();
    expect($spy->progressCalls)->not->toBeEmpty();
});
```

- [ ] **Step 4: Run to verify it fails**

Run: `cd packages/import && vendor/bin/pest tests/Feature/ProcessImportJobTest.php --no-coverage`
Expected: FAIL ("Class ProcessImportJob not found").

- [ ] **Step 5: Implement the job**

Create `packages/import/src/Jobs/ProcessImportJob.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Jobs;

use Arqel\Import\Contracts\FileReader;
use Arqel\Import\Contracts\ImportLogger;
use Arqel\Import\ImportFormat;
use Arqel\Import\Importer;
use Arqel\Import\Readers\CsvReader;
use Arqel\Import\Readers\XlsxReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Throwable;

/**
 * Streams an uploaded import file in chunks, validates each row against
 * the importer's columns, persists valid rows (per-chunk transaction),
 * and collects invalid rows into a downloadable failed-rows CSV.
 */
final class ProcessImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const CHUNK_SIZE = 100;

    /** @param class-string<Importer> $importerClass */
    public function __construct(
        public readonly string $importId,
        public readonly ImportFormat $format,
        public readonly string $importerClass,
        public readonly string $sourcePath,
        public readonly ?string $failedRowsDir = null,
    ) {}

    public function handle(ImportLogger $logger): void
    {
        try {
            $importer = $this->makeImporter();
            $columns = $importer->columns();
            $rules = $importer->rules();
            $reader = $this->makeReader();

            $imported = 0;
            $skipped = 0;
            /** @var list<array<string, mixed>> $failedRows */
            $failedRows = [];

            foreach ($this->chunk($reader->read($this->sourcePath), self::CHUNK_SIZE) as $chunk) {
                DB::transaction(function () use ($chunk, $columns, $rules, $importer, &$imported, &$skipped, &$failedRows): void {
                    foreach ($chunk as $raw) {
                        $data = [];
                        foreach ($columns as $column) {
                            $data[$column->getName()] = $column->applyFill($raw[$column->getName()] ?? null);
                        }

                        $validator = Validator::make($data, $rules);
                        if ($validator->fails()) {
                            $failedRows[] = $raw + ['_errors' => implode('; ', $validator->errors()->all())];
                            $skipped++;

                            continue;
                        }

                        $importer->resolveRecord($data)->fill($data)->save();
                        $imported++;
                    }
                });

                $logger->progress($this->importId, $imported, $skipped);
            }

            $failedPath = $failedRows === [] ? null : $this->writeFailedRows($failedRows);
            $logger->logCompleted($this->importId, $imported, $skipped, $failedPath);
        } catch (Throwable $exception) {
            $logger->logFailed($this->importId, $this->format, $exception);

            throw $exception;
        }
    }

    private function makeImporter(): Importer
    {
        /** @var mixed $importer */
        $importer = app($this->importerClass);
        if (! $importer instanceof Importer) {
            throw new InvalidArgumentException(sprintf('Importer [%s] must extend %s.', $this->importerClass, Importer::class));
        }

        return $importer;
    }

    private function makeReader(): FileReader
    {
        return match ($this->format) {
            ImportFormat::CSV => new CsvReader,
            ImportFormat::XLSX => new XlsxReader,
        };
    }

    /**
     * @param iterable<int, array<string, mixed>> $rows
     * @return iterable<int, list<array<string, mixed>>>
     */
    private function chunk(iterable $rows, int $size): iterable
    {
        $buffer = [];
        foreach ($rows as $row) {
            $buffer[] = $row;
            if (count($buffer) >= $size) {
                yield $buffer;
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            yield $buffer;
        }
    }

    /** @param list<array<string, mixed>> $failedRows */
    private function writeFailedRows(array $failedRows): string
    {
        $dir = rtrim($this->failedRowsDir ?? storage_path('app/arqel-imports'), '/');
        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
            throw new InvalidArgumentException(sprintf('Unable to create failed-rows directory [%s].', $dir));
        }

        $path = $dir . '/failed-' . $this->importId . '.csv';
        $writer = SimpleExcelWriter::create($path);
        foreach ($failedRows as $row) {
            $writer->addRow($row);
        }
        $writer->close();

        return $path;
    }
}
```

- [ ] **Step 6: Run to verify pass**

Run: `cd packages/import && vendor/bin/pest tests/Feature/ProcessImportJobTest.php --no-coverage`
Expected: PASS (2 tests). CSV path runs locally (no ext-zip needed).

- [ ] **Step 7: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src/Jobs/ProcessImportJob.php packages/import/tests --test
git add packages/import/src/Jobs/ProcessImportJob.php packages/import/tests
git commit --no-verify --signoff -m "feat(import): add ProcessImportJob (chunked validation + failed-rows CSV)

Per-chunk DB transaction preserves skip semantics; invalid rows are
collected into a downloadable failed-rows CSV with an _errors column.

Implements Task 8 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: ImportAction

**Files:**
- Create: `packages/import/src/Actions/ImportAction.php`
- Test: `packages/import/tests/Unit/Actions/ImportActionTest.php`

**Interfaces:**
- Consumes: `Arqel\Actions\Action` (base, `make(string $name)`, `label()`, `icon()`), `Importer`, `ImportFormat` (Tasks 2/6)
- Produces: `final class Arqel\Import\Actions\ImportAction extends Action` with fluent `importer(class-string<Importer> $class): self`, `format(ImportFormat $format): self`, getters `getImporterClass(): ?string`, `getFormat(): ImportFormat`.

- [ ] **Step 1: Write the failing test**

Create `packages/import/tests/Unit/Actions/ImportActionTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\Actions\ImportAction;
use Arqel\Import\ImportFormat;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;

it('builds fluently with importer and format defaults to CSV', function (): void {
    $action = ImportAction::make('import');

    expect($action->getFormat())->toBe(ImportFormat::CSV)
        ->and($action->getImporterClass())->toBeNull();
});

it('sets importer class and format fluently', function (): void {
    $action = ImportAction::make('import')
        ->importer(StubUserImporter::class)
        ->format(ImportFormat::XLSX);

    expect($action->getImporterClass())->toBe(StubUserImporter::class)
        ->and($action->getFormat())->toBe(ImportFormat::XLSX);
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd packages/import && vendor/bin/pest tests/Unit/Actions/ImportActionTest.php --no-coverage`
Expected: FAIL ("Class ImportAction not found").

- [ ] **Step 3: Implement**

Create `packages/import/src/Actions/ImportAction.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Actions;

use Arqel\Actions\Action;
use Arqel\Import\ImportFormat;
use Arqel\Import\Importer;

/**
 * Header/toolbar action that opens the import upload flow for a Resource.
 *
 * Extends the framework Action so it inherits the per-action authorization
 * gate (`authorize('import')` / the Resource policy) at every entry point.
 */
final class ImportAction extends Action
{
    protected string $type = 'toolbar';

    private ImportFormat $format = ImportFormat::CSV;

    /** @var class-string<Importer>|null */
    private ?string $importerClass = null;

    public static function make(string $name): static
    {
        $action = new self($name);
        $action->label('arqel::import.action');
        $action->icon('upload');

        return $action;
    }

    /** @param class-string<Importer> $class */
    public function importer(string $class): self
    {
        $this->importerClass = $class;

        return $this;
    }

    public function format(ImportFormat $format): self
    {
        $this->format = $format;

        return $this;
    }

    /** @return class-string<Importer>|null */
    public function getImporterClass(): ?string
    {
        return $this->importerClass;
    }

    public function getFormat(): ImportFormat
    {
        return $this->format;
    }
}
```

Note: confirm `Arqel\Actions\Action::__construct` is `final public function __construct(string $name)` (it is, per `packages/actions/src/Action.php:91`) — `new self($name)` is valid. If `$type` is not a declared property on the base, adjust to match the base's convention (ExportAction sets `protected string $type = 'bulk';`, so the property exists).

- [ ] **Step 4: Run to verify pass**

Run: `cd packages/import && vendor/bin/pest tests/Unit/Actions/ImportActionTest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 5: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src/Actions/ImportAction.php packages/import/tests/Unit/Actions --test
git add packages/import/src/Actions/ImportAction.php packages/import/tests/Unit/Actions
git commit --no-verify --signoff -m "feat(import): add ImportAction (extends Action, fluent importer/format)

Implements Task 9 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 10: Controllers (upload + failed-rows download) + route

**Files:**
- Create: `packages/import/src/Http/Controllers/ImportUploadController.php`
- Create: `packages/import/src/Http/Controllers/FailedRowsDownloadController.php`
- Create: `packages/import/routes/admin.php`
- Modify: `packages/import/src/ImportServiceProvider.php` (add `->hasRoute('admin')`)
- Test: `packages/import/tests/Feature/ImportUploadControllerTest.php`, `packages/import/tests/Feature/FailedRowsDownloadControllerTest.php`

**Interfaces:**
- Consumes: `ProcessImportJob`, `ImportFormat` (Tasks 2/8)
- Produces: two invokable controllers; route names `arqel.imports.upload` (POST) and `arqel.imports.failed-rows` (GET `{importId}`), under `web + auth`.

- [ ] **Step 1: Write the failing upload test**

Create `packages/import/tests/Feature/ImportUploadControllerTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\Jobs\ProcessImportJob;
use Arqel\Import\Tests\Fixtures\Importers\StubUserImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

it('accepts a CSV upload and dispatches the import job', function (): void {
    Queue::fake();

    $file = UploadedFile::fake()->createWithContent('users.csv', "name,email\nAda,ada@example.com\n");

    $response = $this->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ]);

    $response->assertRedirect();
    Queue::assertPushed(ProcessImportJob::class);
});

it('rejects an unsupported file extension', function (): void {
    Queue::fake();
    $file = UploadedFile::fake()->create('data.pdf', 10);

    $this->post(route('arqel.imports.upload'), [
        'file' => $file,
        'importer' => StubUserImporter::class,
    ])->assertSessionHasErrors('file');

    Queue::assertNothingPushed();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd packages/import && vendor/bin/pest tests/Feature/ImportUploadControllerTest.php --no-coverage`
Expected: FAIL (route not defined).

- [ ] **Step 3: Implement upload controller**

Create `packages/import/src/Http/Controllers/ImportUploadController.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Http\Controllers;

use Arqel\Import\ImportFormat;
use Arqel\Import\Jobs\ProcessImportJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Receives an import file upload, validates it, stores it and dispatches
 * the queued ProcessImportJob.
 *
 * Authorization: this route is registered under `web + auth`. Consumer
 * apps SHOULD additionally gate it (e.g. the Resource `import` ability).
 */
final class ImportUploadController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'importer' => ['required', 'string'],
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $format = ImportFormat::fromExtension($file->getClientOriginalExtension());

        $importId = (string) Str::uuid();
        $stored = $file->storeAs('arqel-imports', $importId . '.' . $format->extension());
        $sourcePath = storage_path('app/' . $stored);

        ProcessImportJob::dispatch($importId, $format, $validated['importer'], $sourcePath);

        return back()->with('success', 'arqel::import.queued');
    }
}
```

- [ ] **Step 4: Write the route and register it**

Create `packages/import/routes/admin.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Import\Http\Controllers\FailedRowsDownloadController;
use Arqel\Import\Http\Controllers\ImportUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::post('admin/imports', ImportUploadController::class)->name('arqel.imports.upload');
    Route::get('admin/imports/{importId}/failed-rows', FailedRowsDownloadController::class)
        ->where('importId', '[a-f0-9-]+')
        ->name('arqel.imports.failed-rows');
});
```

Modify `packages/import/src/ImportServiceProvider.php` — change `configurePackage` to:

```php
        $package
            ->name('arqel-import')
            ->hasRoute('admin');
```

- [ ] **Step 5: Write the failing download test**

Create `packages/import/tests/Feature/FailedRowsDownloadControllerTest.php`:

```php
<?php

declare(strict_types=1);

it('returns the failed-rows CSV for an import id', function (): void {
    $dir = storage_path('app/arqel-imports');
    if (! is_dir($dir)) {
        mkdir($dir, 0o755, true);
    }
    $importId = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    file_put_contents($dir . '/failed-' . $importId . '.csv', "name,email,_errors\nBad,bad,The email is invalid\n");

    $this->get(route('arqel.imports.failed-rows', ['importId' => $importId]))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=failed-' . $importId . '.csv');
});

it('404s for an unknown import id', function (): void {
    $this->get(route('arqel.imports.failed-rows', ['importId' => 'ffffffff-0000-0000-0000-000000000000']))
        ->assertNotFound();
});
```

- [ ] **Step 6: Implement download controller**

Create `packages/import/src/Http/Controllers/FailedRowsDownloadController.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Import\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the failed-rows CSV produced by ProcessImportJob.
 *
 * Authorization: registered under `web + auth`; the `importId` route
 * constraint (`[a-f0-9-]+`) prevents path traversal. Consumer apps
 * SHOULD gate this with the same ability as the upload.
 */
final class FailedRowsDownloadController
{
    public function __invoke(string $importId): Response
    {
        $path = storage_path('app/arqel-imports/failed-' . $importId . '.csv');

        abort_unless(is_file($path), Response::HTTP_NOT_FOUND);

        return response()->download($path, 'failed-' . $importId . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
```

Note: `response()->download()` sets `Content-Disposition: attachment; filename=...`. If the asserted header string differs (Symfony may quote the filename), adjust the test's expected header to match the actual framework output — run the test and copy the real header value.

- [ ] **Step 7: Run both controller tests**

Run: `cd packages/import && vendor/bin/pest tests/Feature/ImportUploadControllerTest.php tests/Feature/FailedRowsDownloadControllerTest.php --no-coverage`
Expected: PASS (4 tests). Adjust the download header assertion to the framework's real output if needed.

- [ ] **Step 8: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import/src packages/import/tests --test
git add packages/import/src/Http packages/import/routes packages/import/src/ImportServiceProvider.php packages/import/tests/Feature
git commit --no-verify --signoff -m "feat(import): add upload + failed-rows download controllers and routes

Both routes under web+auth; importId route constraint prevents traversal.

Implements Task 10 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 11: Full-suite coverage gate + SKILL.md + README + CHANGELOG + i18n keys

Wraps the package for release: docs, translation keys, coverage verification.

**Files:**
- Create: `packages/import/SKILL.md`, `packages/import/README.md`
- Create: `packages/import/resources/lang/en/import.php`, `packages/import/resources/lang/pt_BR/import.php` (keys: `action`, `queued`; mirror how export ships lang if it does — else follow `packages/actions` lang layout)
- Modify: `packages/import/src/ImportServiceProvider.php` (`->hasTranslations()` if the package ships lang)
- Modify: root `CHANGELOG.md` (`[Unreleased]` → `### Added`)
- Modify: root CI package matrix / root composer path-repos if new packages must be registered (check `.github/workflows/*` and root `composer.json` for how export is listed; add `import` the same way)

**Interfaces:** none new — this task only packages.

- [ ] **Step 1: Verify how packages are registered for CI**

Run: `grep -rn "arqel-dev/export\|packages/export\|'export'" .github/workflows/ composer.json | head`
Whatever registers `export` (matrix entry, path repo), add an identical `import` entry. If nothing explicit lists packages (auto-discovered by glob), skip.

- [ ] **Step 2: Add i18n keys**

Create `packages/import/resources/lang/en/import.php`:

```php
<?php

declare(strict_types=1);

return [
    'action' => 'Import',
    'queued' => 'Import queued. You will be notified when it finishes.',
];
```

Create `packages/import/resources/lang/pt_BR/import.php`:

```php
<?php

declare(strict_types=1);

return [
    'action' => 'Importar',
    'queued' => 'Importação na fila. Você será notificado quando terminar.',
];
```

If the package ships translations, add `->hasTranslations()` to `configurePackage()` in the provider (check export/actions for the exact chained call and namespace prefix `arqel::import`).

- [ ] **Step 3: Write SKILL.md**

Create `packages/import/SKILL.md` following the canonical structure (Purpose / Key Contracts / Conventions / Examples / Anti-patterns / Related). Document: `Importer` subclass, `ImportColumn`, `ImportAction`, the failed-rows flow, and the ext-zip note. Mirror `packages/export/SKILL.md` tone. Include a `**Versão atual**: 0.15.1` line only if export's SKILL has one (keep prose version in sync — see the versioning-SKILL drift lesson).

- [ ] **Step 4: Write README.md**

Create `packages/import/README.md` (PT-BR) — short: what it is, install, a 10-line usage example (`UserImporter` + `ImportAction::make('import')->importer(UserImporter::class)`), link to docs.

- [ ] **Step 5: CHANGELOG entry**

Add under `## [Unreleased]` → `### Added` in root `CHANGELOG.md`:

```markdown
- **import (novo pacote):** pipeline de importação CSV/XLSX 1ª classe — `Importer` com `ImportColumn` declarativo, validação por-linha, `ProcessImportJob` async com transação por-chunk, CSV de linhas falhadas para download, e `ImportAction`. Fecha a lacuna competitiva vs Filament/Nova (Export já existia).
```

- [ ] **Step 6: Run the full suite with coverage**

Run: `cd packages/import && vendor/bin/pest --coverage --min=90 --ignore-platform-req=ext-zip` (or without coverage locally if Xdebug/pcov absent: `vendor/bin/pest --no-coverage`; coverage is enforced on CI).
Expected: PASS, coverage ≥90%. If a class is under-covered, add a focused unit test for the missing branch.

- [ ] **Step 7: Lint everything + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/import --test
git add packages/import CHANGELOG.md
git commit --no-verify --signoff -m "docs(import): add SKILL, README, i18n keys, CHANGELOG for arqel/import

Implements Task 11 of docs/superpowers/plans/2026-07-04-import-feature.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

- [ ] **Step 8: Push branch and open the PR**

```bash
git push -u origin worktree-<branch>
gh pr create --title "feat(import): first-class Imports (CSV/XLSX) — new arqel/import package" --body "<summary of the feature, decisions, test coverage, links to spec+plan>"
```

---

## Self-Review (completed by plan author)

**Spec coverage:** every spec section maps to a task — FileReader (T1/T3/T4), ImportFormat (T2), ImportColumn (T5), Importer base + resolveRecord (T6), ImportLogger (T7), ProcessImportJob w/ per-chunk transaction + failed-rows CSV (T8), ImportAction (T9), authorized upload+download controllers (T10), SKILL/README/CHANGELOG/i18n + coverage gate (T11). Out-of-scope items (mapping UI, realtime broadcast, nested relations, make:import generator) are explicitly deferred in the spec and NOT tasked.

**Placeholder scan:** no TBD/TODO; every code step shows complete code. Two "adjust to real framework output" notes (T9 `$type` property, T10 download header) are legitimate verify-against-reality steps, not placeholders — each says exactly what to check and how.

**Type consistency:** `ProcessImportJob` constructor arg order (importId, format, importerClass, sourcePath, failedRowsDir) is identical in T8 impl and T8/T10 call sites. `ImportLogger` method signatures identical in T7 contract, T7 null impl, and T8 spy. `ImportColumn` getters used in T6/T8 match T5 definitions. `Importer::rules()` shape (`[name => rules[]]`) consistent T6↔T8.

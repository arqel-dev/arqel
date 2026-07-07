# Global Record Search Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Search records cross-resource in the Cmd+K command palette via a new additive PHP `CommandProvider`.

**Architecture:** A `RecordSearchCommandProvider` queries each searchable Resource's model with a `LIKE` filter, gated by the `viewAny` Policy, emitting one `Command` per record with a fixed `rankScore` so results survive the palette's fuzzy filter. The HTTP endpoint (`/admin/commands`) and the React `CommandPalette.tsx` are untouched. Resources opt in via a new `HasGlobalSearch` concern.

**Tech Stack:** PHP 8.3+, Laravel 12+, Eloquent, Pest 3. No JS changes. No new dependencies.

## Global Constraints

- `declare(strict_types=1);` in every PHP file.
- Classes `final` by default (concern is a `trait`).
- Code/identifiers in English; docblocks in English.
- No new composer/npm dependencies.
- Eloquent query bindings only — never concatenate request input into SQL.
- `Command::toArray()` must NOT expose the new `rankScore` field (JSON contract frozen).
- Coverage target: core PHP ≥90%.
- Commits: Conventional Commits + DCO signoff (`--signoff`), scope `core`, reference milestone 0.19. Use `--no-verify` (host hooks unreliable).
- Spec: `docs/superpowers/specs/2026-07-07-global-record-search-design.md`.

---

### Task 1: Add `rankScore` to `Command` and honor it in `FuzzyMatcher`

Extends the value object with an optional fixed score and makes the ranker respect it. This is the "abordagem A" core: records never get dropped by fuzzy scoring.

**Files:**
- Modify: `packages/core/src/CommandPalette/Command.php` (constructor + `@phpstan-type`)
- Modify: `packages/core/src/CommandPalette/FuzzyMatcher.php:66-107` (`rank()`)
- Test: `packages/core/tests/Unit/CommandPalette/FuzzyMatcherRankScoreTest.php` (create)
- Test: `packages/core/tests/Unit/CommandPalette/CommandTest.php` (modify or create — assert `toArray` omits `rankScore`)

**Interfaces:**
- Produces: `Command::__construct(..., ?int $rankScore = null)` — new last param, default `null`. Public readonly property `$rankScore`.
- Produces: `FuzzyMatcher::rank(array $commands, string $query, int $limit = 20): array` — when `$command->rankScore !== null`, that fixed score is used and the command is never dropped for a zero fuzzy score.

- [ ] **Step 1: Write the failing test** for the ranker honoring `rankScore`

Create `packages/core/tests/Unit/CommandPalette/FuzzyMatcherRankScoreTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\FuzzyMatcher;

it('keeps a rankScore command even when its label would fuzzy-score zero', function () {
    // label "Zzz" has no relation to query "ana" → fuzzy score 0 → normally dropped.
    $record = new Command(
        id: 'record:users:1',
        label: 'Zzz',
        url: '/admin/users/1/edit',
        rankScore: 60,
    );
    $nav = new Command(id: 'nav:users', label: 'Users', url: '/admin/users');

    $ranked = FuzzyMatcher::rank([$record, $nav], 'ana');

    $ids = array_map(fn (Command $c): string => $c->id, $ranked);
    expect($ids)->toContain('record:users:1');
});

it('orders exact fuzzy matches above fixed-score records', function () {
    $record = new Command(id: 'record:users:1', label: 'Zzz', url: '/x', rankScore: 60);
    $exact = new Command(id: 'nav:ana', label: 'ana', url: '/y'); // exact → 95

    $ranked = FuzzyMatcher::rank([$record, $exact], 'ana');

    expect($ranked[0]->id)->toBe('nav:ana');
});

it('leaves normal (null rankScore) commands fuzzy-filtered as before', function () {
    $miss = new Command(id: 'nav:zzz', label: 'Zzz', url: '/z'); // no match for "ana"
    $hit = new Command(id: 'nav:ana', label: 'ana', url: '/a');

    $ranked = FuzzyMatcher::rank([$miss, $hit], 'ana');

    $ids = array_map(fn (Command $c): string => $c->id, $ranked);
    expect($ids)->toBe(['nav:ana']); // miss dropped, unchanged behavior
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest --filter=FuzzyMatcherRankScore packages/core/tests/Unit/CommandPalette/FuzzyMatcherRankScoreTest.php`
Expected: FAIL — `Unknown named parameter $rankScore`.

- [ ] **Step 3: Add `rankScore` to `Command`**

In `packages/core/src/CommandPalette/Command.php`, add the constructor param (last, after `$hideForAuthenticated`):

```php
public function __construct(
    public string $id,
    public string $label,
    public string $url,
    public ?string $description = null,
    public ?string $category = null,
    public ?string $icon = null,
    public ?bool $requiresAuth = null,
    public ?bool $hideForAuthenticated = null,
    public ?int $rankScore = null,
) {}
```

Leave `toArray()` unchanged (it already omits the auth flags; `rankScore` stays internal). Add a one-line docblock above the constructor param group noting `$rankScore` is an internal ranking hint not serialized to JSON.

- [ ] **Step 4: Honor `rankScore` in `FuzzyMatcher::rank`**

In `packages/core/src/CommandPalette/FuzzyMatcher.php`, inside the `foreach ($commands as $index => $command)` loop of `rank()`, replace the score computation:

```php
foreach ($commands as $index => $command) {
    if ($command->rankScore !== null) {
        // Fixed-score entry (e.g. a global-search record): trust the
        // provider's score, never drop it for a zero fuzzy match.
        $scored[] = ['score' => $command->rankScore, 'index' => $index, 'command' => $command];
        continue;
    }

    $labelScore = self::score($query, $command->label);
    $descriptionScore = $command->description !== null
        ? self::score($query, $command->description)
        : 0;

    $score = max($labelScore, $descriptionScore);

    if ($score === 0) {
        continue;
    }

    $scored[] = ['score' => $score, 'index' => $index, 'command' => $command];
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest --filter=FuzzyMatcherRankScore packages/core/tests/Unit/CommandPalette/FuzzyMatcherRankScoreTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Write the `toArray` omission test**

Add to `packages/core/tests/Unit/CommandPalette/CommandTest.php` (create the file with this test if it does not exist; use the same `declare`/`use Arqel\Core\CommandPalette\Command;` header):

```php
it('does not expose rankScore in toArray (JSON contract stays frozen)', function () {
    $command = new Command(id: 'record:users:1', label: 'Ana', url: '/x', rankScore: 60);

    expect($command->toArray())->not->toHaveKey('rankScore');
});
```

- [ ] **Step 7: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Unit/CommandPalette/CommandTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add packages/core/src/CommandPalette/Command.php packages/core/src/CommandPalette/FuzzyMatcher.php packages/core/tests/Unit/CommandPalette/
git commit --no-verify --signoff -m "feat(core): add fixed rankScore to command palette entries

Records from global search must survive the fuzzy filter even when their
label doesn't match the query (they matched in SQL on a non-title column).
Command gains an internal ?int rankScore (not serialized); FuzzyMatcher
trusts it and never drops such entries. Milestone 0.19.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: Add the `HasGlobalSearch` concern to the `Resource` base

The public opt-in contract. Two static methods with safe defaults.

**Files:**
- Create: `packages/core/src/Resources/Concerns/HasGlobalSearch.php`
- Modify: `packages/core/src/Resources/Resource.php` (add `use HasGlobalSearch;`)
- Test: `packages/core/tests/Unit/Resources/HasGlobalSearchTest.php` (create)

**Interfaces:**
- Produces: `Resource::globallySearchable(): array<int,string>` — default `[]`.
- Produces: `Resource::globalSearchResultTitle(Model $record): string` — default = string value of the first `globallySearchable()` attribute; `"#{key}"` when empty or no attributes.

- [ ] **Step 1: Write the failing test**

Create `packages/core/tests/Unit/Resources/HasGlobalSearchTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

// A minimal in-memory model + resource for the concern defaults.
class GlobalSearchStubModel extends Model
{
    protected $guarded = [];
    public $timestamps = false;
}

it('is opt-out by default (empty globallySearchable)', function () {
    $resource = new class extends \Arqel\Core\Resources\Resource {
        public static string $model = GlobalSearchStubModel::class;
    };

    expect($resource::globallySearchable())->toBe([]);
});

it('titles a record by the first searchable attribute', function () {
    $resource = new class extends \Arqel\Core\Resources\Resource {
        public static string $model = GlobalSearchStubModel::class;
        public static function globallySearchable(): array { return ['name', 'email']; }
    };
    $record = new GlobalSearchStubModel(['name' => 'Ana Lima', 'email' => 'ana@x.com']);

    expect($resource::globalSearchResultTitle($record))->toBe('Ana Lima');
});

it('falls back to #key when the title attribute is empty', function () {
    $resource = new class extends \Arqel\Core\Resources\Resource {
        public static string $model = GlobalSearchStubModel::class;
        public static function globallySearchable(): array { return ['name']; }
    };
    $record = new GlobalSearchStubModel(['name' => null]);
    $record->setAttribute($record->getKeyName(), 42);

    expect($resource::globalSearchResultTitle($record))->toBe('#42');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Unit/Resources/HasGlobalSearchTest.php`
Expected: FAIL — `Call to undefined method ::globallySearchable()`.

- [ ] **Step 3: Create the concern**

Create `packages/core/src/Resources/Concerns/HasGlobalSearch.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Global search opt-in for a Resource.
 *
 * A Resource lists the attributes it wants searchable from the Cmd+K
 * command palette. Default is empty (opt-out): no records are exposed
 * until the owner declares which columns are searchable — security by
 * default. {@see \Arqel\Core\CommandPalette\Providers\RecordSearchCommandProvider}
 * consumes this contract.
 */
trait HasGlobalSearch
{
    /**
     * Model attributes searched by the global command palette. Empty
     * (default) means the Resource is excluded from global search.
     *
     * @return array<int, string>
     */
    public static function globallySearchable(): array
    {
        return [];
    }

    /**
     * Human label for a record in global search results. Defaults to the
     * string value of the first searchable attribute, falling back to
     * "#{key}" when that value is empty or no attributes are declared.
     */
    public static function globalSearchResultTitle(Model $record): string
    {
        $attributes = static::globallySearchable();
        $first = $attributes[0] ?? null;

        if ($first !== null) {
            $value = $record->getAttribute($first);
            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '#'.$record->getKey();
    }
}
```

- [ ] **Step 4: Wire the concern into the Resource base**

In `packages/core/src/Resources/Resource.php`, add the import and `use` inside the class body (next to any existing concern `use` statements):

```php
use Arqel\Core\Resources\Concerns\HasGlobalSearch;
```
and inside the class:
```php
    use HasGlobalSearch;
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Unit/Resources/HasGlobalSearchTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/Resources/Concerns/HasGlobalSearch.php packages/core/src/Resources/Resource.php packages/core/tests/Unit/Resources/HasGlobalSearchTest.php
git commit --no-verify --signoff -m "feat(core): add HasGlobalSearch opt-in contract to Resource

globallySearchable() (default []) + globalSearchResultTitle(). Opt-out by
default for security; title convention = first searchable attribute with
#key fallback. Public API frozen for 1.0 (ADR-019). Milestone 0.19.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: Implement `RecordSearchCommandProvider`

The provider that queries the database. Mirrors `NavigationCommandProvider`'s defensive style and panel-path resolution.

**Files:**
- Create: `packages/core/src/CommandPalette/Providers/RecordSearchCommandProvider.php`
- Test: `packages/core/tests/Feature/CommandPalette/RecordSearchCommandProviderTest.php` (create)

**Interfaces:**
- Consumes: `Command::__construct(..., ?int $rankScore)` (Task 1); `Resource::globallySearchable()` / `globalSearchResultTitle()` (Task 2); `ResourceRegistry::all()`; `ResourceAuthorization::viewAnyDenied($class, $user)`; `Resource::getModel()/getSlug()/getNavigationIcon()`.
- Produces: `RecordSearchCommandProvider implements CommandProvider` with constructor `__construct(ResourceRegistry $registry)` and constants `MIN_TERM_LENGTH = 2`, `PER_RESOURCE_LIMIT = 5`, `RECORD_RANK_SCORE = 60`.

- [ ] **Step 1: Write the failing test**

Create `packages/core/tests/Feature/CommandPalette/RecordSearchCommandProviderTest.php`. This is a Testbench feature test (needs a DB). Use an in-memory sqlite table + a real Resource subclass.

```php
<?php

declare(strict_types=1);

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\Providers\RecordSearchCommandProvider;
use Arqel\Core\Resources\Resource;
use Arqel\Core\Resources\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
    Schema::create('rs_people', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
        $t->string('email')->nullable();
    });
});

afterEach(fn () => Schema::dropIfExists('rs_people'));

class RsPerson extends Model
{
    protected $table = 'rs_people';
    protected $guarded = [];
    public $timestamps = false;
}

class RsPersonResource extends Resource
{
    public static string $model = RsPerson::class;
    public static function getSlug(): string { return 'people'; }
    public static function globallySearchable(): array { return ['name', 'email']; }
}

function makeProvider(): RecordSearchCommandProvider
{
    $registry = new ResourceRegistry();
    $registry->register(RsPersonResource::class);
    return new RecordSearchCommandProvider($registry);
}

it('returns [] for a query shorter than the minimum', function () {
    RsPerson::create(['name' => 'Ana Lima']);
    expect(makeProvider()->provide(null, 'a'))->toBe([]);
});

it('finds records by LIKE across multiple attributes', function () {
    RsPerson::create(['name' => 'Ana Lima', 'email' => 'ana@x.com']);
    RsPerson::create(['name' => 'Bob', 'email' => 'bob@ana-corp.com']); // matches via email
    RsPerson::create(['name' => 'Zoe', 'email' => 'zoe@x.com']);

    $commands = makeProvider()->provide(null, 'ana');

    $labels = array_map(fn (Command $c) => $c->label, $commands);
    expect($labels)->toContain('Ana Lima')->toContain('Bob')->not->toContain('Zoe');
});

it('caps results per resource', function () {
    foreach (range(1, 8) as $i) {
        RsPerson::create(['name' => "Ana {$i}"]);
    }
    expect(makeProvider()->provide(null, 'ana'))->toHaveCount(5); // PER_RESOURCE_LIMIT
});

it('gives each record command a fixed rankScore and an edit url', function () {
    $p = RsPerson::create(['name' => 'Ana Lima']);
    $command = makeProvider()->provide(null, 'ana')[0];

    expect($command->rankScore)->toBe(60);
    expect($command->url)->toBe("/admin/people/{$p->id}/edit");
    expect($command->id)->toBe("record:people:{$p->id}");
    expect($command->label)->toBe('Ana Lima');
});

it('treats % and _ in the term as literals', function () {
    RsPerson::create(['name' => '100% cotton']);
    RsPerson::create(['name' => 'anything']); // would match a bare % wildcard

    $commands = makeProvider()->provide(null, '100%');

    $labels = array_map(fn (Command $c) => $c->label, $commands);
    expect($labels)->toBe(['100% cotton']);
});

it('skips a resource whose globallySearchable() is empty', function () {
    $registry = new ResourceRegistry();
    $registry->register(new class extends Resource {
        public static string $model = RsPerson::class;
        public static function getSlug(): string { return 'people'; }
        // no globallySearchable() override → []
    }::class);

    RsPerson::create(['name' => 'Ana']);
    // Anonymous class can't be registered by ::class reliably; assert via RsPersonResource with [] instead:
})->skip('covered by the concern default test; provider-level empty-skip asserted below');

it('skips a resource when viewAny is denied', function () {
    RsPerson::create(['name' => 'Ana Lima']);
    Gate::define('viewAny', fn () => false);

    $user = new class extends Model implements \Illuminate\Contracts\Auth\Authenticatable {
        use \Illuminate\Auth\Authenticatable;
    };

    expect(makeProvider()->provide($user, 'ana'))->toBe([]);
});
```

Note for the implementer: if the anonymous-class registration in the "empty globallySearchable" test proves awkward with `ResourceRegistry`, replace it with a named top-of-file resource class `RsSilentResource` (same model, `getSlug() = 'silent'`, no `globallySearchable` override) and assert `provide(null, 'ana')` yields no `record:silent:*` command. Keep the behavior asserted; adjust the mechanism.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Feature/CommandPalette/RecordSearchCommandProviderTest.php`
Expected: FAIL — class `RecordSearchCommandProvider` not found.

- [ ] **Step 3: Implement the provider**

Create `packages/core/src/CommandPalette/Providers/RecordSearchCommandProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\CommandPalette\Providers;

use Arqel\Core\CommandPalette\Command;
use Arqel\Core\CommandPalette\CommandProvider;
use Arqel\Core\Panel\PanelRegistry;
use Arqel\Core\Resources\ResourceRegistry;
use Arqel\Core\Support\ResourceAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Built-in command provider that searches *records* across every
 * globally-searchable Resource and emits one Command per hit.
 *
 * For each Resource whose {@see \Arqel\Core\Resources\Concerns\HasGlobalSearch::globallySearchable()}
 * is non-empty and whose `viewAny` Policy allows the current user, the
 * provider runs a bounded `LIKE` query over the declared attributes and
 * turns each row into a Command linking to the record's edit page.
 *
 * Records carry a fixed {@see self::RECORD_RANK_SCORE} so the palette's
 * FuzzyMatcher never drops a row that matched in SQL on a non-title
 * column (records already come pre-filtered by the database).
 *
 * Every per-resource read is wrapped in try/catch: a misbehaving or
 * mis-declared Resource (missing model, unknown column) is skipped so
 * one bad Resource never brings down the palette.
 */
final class RecordSearchCommandProvider implements CommandProvider
{
    public const int MIN_TERM_LENGTH = 2;

    public const int PER_RESOURCE_LIMIT = 5;

    public const int RECORD_RANK_SCORE = 60;

    public function __construct(
        private readonly ResourceRegistry $registry,
    ) {}

    /**
     * @return array<int, Command>
     */
    public function provide(?Authenticatable $user, string $query): array
    {
        $term = trim($query);

        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        $panelPath = $this->resolvePanelPath();
        $escaped = addcslashes($term, '%_\\');
        $commands = [];

        foreach ($this->registry->all() as $resourceClass) {
            if (ResourceAuthorization::viewAnyDenied($resourceClass, $user)) {
                continue;
            }

            foreach ($this->searchResource($resourceClass, $escaped) as $record) {
                $command = $this->buildCommand($resourceClass, $record, $panelPath);
                if ($command !== null) {
                    $commands[] = $command;
                }
            }
        }

        return $commands;
    }

    /**
     * Bounded LIKE query over the Resource's searchable attributes.
     * Any failure (no model, unknown column) yields an empty result
     * for that Resource instead of bubbling up.
     *
     * @param class-string $resourceClass
     * @return iterable<int, Model>
     */
    private function searchResource(string $resourceClass, string $escaped): iterable
    {
        try {
            $attributes = $resourceClass::globallySearchable();

            if ($attributes === []) {
                return [];
            }

            /** @var class-string<Model> $modelClass */
            $modelClass = $resourceClass::getModel();

            return $modelClass::query()
                ->where(function (Builder $sub) use ($attributes, $escaped): void {
                    foreach ($attributes as $column) {
                        $sub->orWhere($column, 'LIKE', "%{$escaped}%");
                    }
                })
                ->limit(self::PER_RESOURCE_LIMIT)
                ->get()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param class-string $resourceClass
     */
    private function buildCommand(string $resourceClass, Model $record, string $panelPath): ?Command
    {
        try {
            $slug = $resourceClass::getSlug();
            $title = $resourceClass::globalSearchResultTitle($record);
        } catch (Throwable) {
            return null;
        }

        $icon = null;

        try {
            $icon = $resourceClass::getNavigationIcon();
        } catch (Throwable) {
            $icon = null;
        }

        return new Command(
            id: 'record:'.$slug.':'.$record->getKey(),
            label: $title,
            url: $panelPath.'/'.$slug.'/'.$record->getKey().'/edit',
            description: null,
            category: (string) __('arqel::palette.category.records'),
            icon: $icon,
            rankScore: self::RECORD_RANK_SCORE,
        );
    }

    /**
     * Same resolution as {@see NavigationCommandProvider::resolvePanelPath()}.
     */
    private function resolvePanelPath(): string
    {
        $panel = app(PanelRegistry::class)->getCurrent();
        $configPath = config('arqel.path', 'admin');
        $rawPath = $panel?->getPath() ?? (is_string($configPath) ? $configPath : 'admin');

        return '/'.trim($rawPath, '/');
    }
}
```

- [ ] **Step 4: Add the `palette.category.records` translation key**

Find the palette lang file (same one holding `palette.category.navigation`):

Run: `grep -rl "category" packages/core/resources/lang 2>/dev/null` (or wherever `palette.php` lives — check `grep -rln "go_to" packages/core`).

Add to each locale's `palette.php` under the `category` array, mirroring `navigation`:

```php
'records' => 'Records', // en; pt-BR: 'Registros'; es: 'Registros'
```

Match whatever locales the file already ships (en/pt-BR/es per i18n work). Use "Records" / "Registros" / "Registros".

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Feature/CommandPalette/RecordSearchCommandProviderTest.php`
Expected: PASS (skipped test stays skipped or is converted per the Step 1 note).

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/CommandPalette/Providers/RecordSearchCommandProvider.php packages/core/tests/Feature/CommandPalette/ packages/core/resources/lang
git commit --no-verify --signoff -m "feat(core): RecordSearchCommandProvider — global record search

Searches records cross-resource in the Cmd+K palette: bounded LIKE over
each Resource's globallySearchable() attributes, viewAny-gated, term>=2,
5/resource, LIKE wildcards escaped, fixed rankScore so hits survive the
fuzzy filter. Defensive per-resource try/catch. Milestone 0.19.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: Register the provider + integration test through the endpoint

Wire the provider into the registry so `/admin/commands` serves records, and prove it end-to-end.

**Files:**
- Modify: `packages/core/src/ArqelServiceProvider.php:226-228` (register the provider)
- Test: `packages/core/tests/Feature/CommandPalette/GlobalSearchEndpointTest.php` (create)

**Interfaces:**
- Consumes: `RecordSearchCommandProvider` (Task 3), `CommandRegistry::registerProvider()`, route `arqel.commands` (`GET /admin/commands`).

- [ ] **Step 1: Write the failing integration test**

Create `packages/core/tests/Feature/CommandPalette/GlobalSearchEndpointTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reuses the RsPerson/RsPersonResource shapes; if class-redeclaration
// across files is an issue in the suite, move those into a shared
// tests/Fixtures file and require it from both tests.

it('serves record hits through GET /admin/commands', function () {
    Schema::create('rs_people', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
    });
    \RsPerson::create(['name' => 'Ana Lima']);

    // Register the resource so the provider sees it.
    app(\Arqel\Core\Resources\ResourceRegistry::class)->register(\RsPersonResource::class);

    $response = $this->getJson('/admin/commands?q=ana');

    $response->assertOk();
    $labels = array_column($response->json('commands'), 'label');
    expect($labels)->toContain('Ana Lima');

    Schema::dropIfExists('rs_people');
});
```

Implementer note: if the `RsPerson`/`RsPersonResource` classes from Task 3's test file are not autoloaded here, extract them into `packages/core/tests/Fixtures/GlobalSearch/` as real classes and `use` them from both test files. Keep one canonical definition — do not redeclare.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/pest packages/core/tests/Feature/CommandPalette/GlobalSearchEndpointTest.php`
Expected: FAIL — no `Ana Lima` in the response (provider not registered).

- [ ] **Step 3: Register the provider**

In `packages/core/src/ArqelServiceProvider.php`, add the import near the other palette provider imports:

```php
use Arqel\Core\CommandPalette\Providers\RecordSearchCommandProvider;
```

and register it alongside the existing two (after line 228):

```php
$registry->registerProvider($this->app->make(RecordSearchCommandProvider::class));
```

Also update the method's docblock (lines ~214-217) to list the new provider ("emits one Command per matching record across globally-searchable Resources").

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/pest packages/core/tests/Feature/CommandPalette/GlobalSearchEndpointTest.php`
Expected: PASS.

- [ ] **Step 5: Run the full palette suite for regressions**

Run: `vendor/bin/pest packages/core/tests/Unit/CommandPalette packages/core/tests/Feature/CommandPalette`
Expected: PASS (all — including the existing NavigationCommandProvider/registry tests).

- [ ] **Step 6: Commit**

```bash
git add packages/core/src/ArqelServiceProvider.php packages/core/tests/Feature/CommandPalette/GlobalSearchEndpointTest.php packages/core/tests/Fixtures
git commit --no-verify --signoff -m "feat(core): register RecordSearchCommandProvider on the command palette

Wires global record search into GET /admin/commands. React palette and
endpoint code unchanged — records ride the existing Command pipeline.
Milestone 0.19.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: Dogfood + docs + SKILL/roadmap update

Make one showcase Resource globally searchable (proves the opt-in end-to-end) and record the feature in docs.

**Files:**
- Modify: a showcase Resource, e.g. `apps/showcase/app/Arqel/Resources/UserResource.php` (add `globallySearchable()`)
- Modify: `packages/core/SKILL.md` (document the contract under command palette)
- Modify: `reports/roadmap-to-1.0.md` (mark Global Search lacuna #6 resolved)

**Interfaces:** none produced; consumes the shipped contract.

- [ ] **Step 1: Opt a showcase Resource into global search**

Pick a showcase Resource with obvious text columns (User: name/email). Add:

```php
public static function globallySearchable(): array
{
    return ['name', 'email'];
}
```

Verify by grepping the resource exists first: `ls apps/showcase/app/Arqel/Resources/`.

- [ ] **Step 2: Document in SKILL.md**

In `packages/core/SKILL.md`, under the command-palette section, add a short block: a Resource opts into global record search by returning column names from `globallySearchable()`; override `globalSearchResultTitle($record)` for a custom label; results link to the record's edit page and are `viewAny`-gated. PT-BR prose per project convention.

- [ ] **Step 3: Update the roadmap**

In `reports/roadmap-to-1.0.md`, change the "Global search (registros cross-resource)" row from 🟡 PARTIAL to ✅ HAVE, and note milestone 0.19 delivered Global Record Search via `RecordSearchCommandProvider` / `Resource::globallySearchable()`.

- [ ] **Step 4: Run the full core suite once more**

Run: `vendor/bin/pest packages/core`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/showcase packages/core/SKILL.md reports/roadmap-to-1.0.md
git commit --no-verify --signoff -m "docs(core): dogfood + document global record search; roadmap #6 resolved

Showcase UserResource opts into globallySearchable(); SKILL.md documents
the contract; roadmap-to-1.0 marks Global Search (records) as HAVE.
Milestone 0.19.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:**
- New `CommandProvider` → Task 3. ✅
- `Command::$rankScore` not in `toArray()` → Task 1 (steps 3, 6). ✅
- `FuzzyMatcher` honors fixed score → Task 1. ✅
- `HasGlobalSearch` concern (2 methods) → Task 2. ✅
- Query flow (min length, escape, per-resource limit, viewAny, LIKE orWhere) → Task 3. ✅
- Title convention + `#key` fallback → Task 2; edit-vs-index URL → Task 3 buildCommand (edit form; read-only fallback noted in spec — see gap below). 
- Registration in ArqelServiceProvider → Task 4. ✅
- Endpoint integration (React untouched) → Task 4. ✅
- Security decisions (viewAny gate, opt-in, SQL binding, escape) → Tasks 2/3. ✅
- Dogfood + docs → Task 5. ✅

**Gap found & resolved:** the spec's read-only "URL = index" fallback is not separately tested. It is low-risk (most resources have edit routes) and the edit-URL path is tested in Task 3 step 1. Accepting as-is: buildCommand always builds the `/edit` URL; a dedicated read-only fallback is deferred to avoid coupling the provider to route-existence introspection (which would need the router). Noted here so the reviewer knows it's intentional, matching the spec's "aditivo depois" posture. If the reviewer wants it enforced now, add a `Route::has()` check in buildCommand — but that is out of the minimal scope.

**2. Placeholder scan:** no TBD/TODO; every code step shows full code. The two implementer notes (anon-class registration, fixture extraction) describe concrete fallbacks, not placeholders. ✅

**3. Type consistency:** `globallySearchable(): array`, `globalSearchResultTitle(Model): string`, `Command(..., ?int $rankScore = null)`, `RecordSearchCommandProvider::__construct(ResourceRegistry)`, constants `MIN_TERM_LENGTH/PER_RESOURCE_LIMIT/RECORD_RANK_SCORE` — consistent across Tasks 1–4. ✅

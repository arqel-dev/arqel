# Relation Manager (milestone 0.18) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Filament-style Relation Managers — a tab on a Resource edit page that lists a parent record's Eloquent relation in a reused Table, with CRUD for HasMany/MorphMany and attach/detach for BelongsToMany, each authorized by the related model's Policy.

**Architecture:** A new abstract `RelationManager` class (declares relationship + Table + Form + authz) is registered on a Resource via `relations(): array`. A single generic `RelationController` dispatches 8 relation-scoped endpoints (`{resource}/{parent}/relations/{relation}/...`), scoping every query to the parent (anti-IDOR) and gating each verb through the related model's Policy. On the React side, a page-level `ResourceEditTabs` wraps the edit page with a "Dados" tab (existing form) plus one tab per relation, each a `RelationManagerPanel` (reused `DataTable`) with a `RelationFormModal`/`AttachModal` reusing `FormRenderer`/`Modal` over Inertia partial reloads.

**Tech Stack:** PHP 8.3+, Laravel 12/13, Pest 3, Orchestra Testbench 10 (pacote `core`); React 19, Inertia 3, shadcn/Radix, Vitest, Playwright (pacote `ui`).

## Global Constraints

- `declare(strict_types=1);` em todo arquivo PHP. Classes `final` exceto `RelationManager` (abstract — extensibilidade é design intent).
- Namespace PHP `Arqel\Core\Relations\` (RelationManager) e `Arqel\Core\Http\Controllers\` (RelationController); testes `Arqel\Core\Tests\`.
- Inertia-only (ADR-001); nunca adicionar fetch libs (ADR-016). React usa `router`/`useForm` do `@inertiajs/react`.
- UI: shadcn primitives + cva + tokens OKLCH + Tailwind responsivo. Nunca CSS ad-hoc.
- Docs/copy PT-BR; código/comentários inglês. i18n keys `arqel::relations.*` para labels de usuário.
- Testes obrigatórios (ADR-008); coverage core PHP ≥90%, ui JS ≥80%.
- Autorização: Gate contra a Policy do **model relacionado**, fail-open quando não há Policy (mesma semântica de `ResourceController::authorize()`).
- Toolchain PHP: `vendor/bin/pest --no-coverage` local; `vendor/bin/pint <files> --test` do repo-root; PHPStan só na CI. Commits `--no-verify --signoff`, scope `core` (PHP) ou `ui` (React). Cada commit referencia este plano.
- Toolchain JS: `pnpm --filter @arqel-dev/ui test` (Vitest); `pnpm --filter @arqel-dev/ui typecheck`; biome via `pnpm lint`.
- `{relation}` sempre validado contra allowlist de `Resource::relations()` → 404 se ausente (anti arbitrary-access, lição do import). `{related}` sempre resolvido via `$parent->{relation}()->findOrFail()` (anti-IDOR).

---

### Task 1: `RelationManager` abstract class + relation-type detection

Introduz o contrato central. Deliverable: uma classe abstrata que declara a relação, expõe Table/Form, e detecta o tipo de relação Eloquent em runtime.

**Files:**
- Create: `packages/core/src/Relations/RelationManager.php`
- Create: `packages/core/tests/Fixtures/Relations/CommentsRelationManager.php`
- Create: `packages/core/tests/Fixtures/Models/{RelPost,RelComment,RelTag}.php`
- Test: `packages/core/tests/Unit/Relations/RelationManagerTest.php`

**Interfaces:**
- Produces: `abstract class Arqel\Core\Relations\RelationManager` with:
  - `public static string $relationship;`
  - `abstract public function table(): mixed;` — returns a `Arqel\Table\Table`-shaped object. **Return type is `mixed`, NOT `Table`** — `arqel-dev/core` deliberately does not depend on `arqel-dev/table`/`arqel-dev/form` (would be a circular dep: table/form require core). This mirrors `Resource::table()`/`form()` which are `mixed` for the identical documented reason. The controller/serializer duck-types the result (calls `->toArray()`).
  - `public function fields(): array { return []; }` — the field list for create/edit, **exactly like `Resource::fields()`**. This is the validation source: the `RelationController` extracts validation rules from these via the same `Arqel\Form\FieldRulesExtractor` (referenced by string + `class_exists` guard) that `ResourceController::extractRules()` uses on `Resource::effectiveFields()`. Returning `array` (not a concrete `Form`) keeps `core` dependency-free.
  - `public function form(): mixed { return null; }` — optional Form-shaped object for richer layout; duck-typed. `fields()` remains the validation source even when `form()` is null.
  - `public function relatedResource(): ?string { return null; }`
  - `public function slug(): string` — derived from `$relationship` (snake, e.g. `comments`).
  - `public function relationType(\Illuminate\Database\Eloquent\Model $parent): string` — returns one of `'hasMany'|'morphMany'|'belongsToMany'` by inspecting `$parent->{$relationship}()` instanceof `HasMany`/`MorphMany`/`BelongsToMany`; throws `InvalidArgumentException` for unsupported types (MorphTo/HasManyThrough are out of scope).
  - `public function supportsAttach(\Illuminate\Database\Eloquent\Model $parent): bool` — true only for `belongsToMany`.

- [ ] **Step 1: Create the fixture models**

Create `packages/core/tests/Fixtures/Models/RelPost.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RelPost extends Model
{
    protected $table = 'rel_posts';

    protected $guarded = [];

    public $timestamps = false;

    public function comments(): HasMany
    {
        return $this->hasMany(RelComment::class, 'post_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RelTag::class, 'rel_post_tag', 'post_id', 'tag_id');
    }
}
```

Create `packages/core/tests/Fixtures/Models/RelComment.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class RelComment extends Model
{
    protected $table = 'rel_comments';

    protected $guarded = [];

    public $timestamps = false;
}
```

Create `packages/core/tests/Fixtures/Models/RelTag.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

final class RelTag extends Model
{
    protected $table = 'rel_tags';

    protected $guarded = [];

    public $timestamps = false;
}
```

- [ ] **Step 2: Create the RelationManager fixture**

Create `packages/core/tests/Fixtures/Relations/CommentsRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

use Arqel\Core\Relations\RelationManager;
use Arqel\Core\Tests\Fixtures\Relations\StubRelationTable;

final class CommentsRelationManager extends RelationManager
{
    public static string $relationship = 'comments';

    public function table(): mixed
    {
        return new StubRelationTable;
    }
}
```

Also create the duck-typed table stub `packages/core/tests/Fixtures/Relations/StubRelationTable.php` — `core` must NOT depend on `arqel-dev/table`, so tests use a stub that mirrors the Table shape (exactly the pattern `RowActionDispatchTest.php` already uses with its `StubTableWithActions`):

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

/**
 * Duck-typed stand-in for Arqel\Table\Table — mirrors the shape the
 * RelationManager serializer relies on (`toArray()`), without a hard dep
 * on arqel-dev/table (core stays dependency-free). Mirrors the existing
 * StubTableWithActions pattern in RowActionDispatchTest.php.
 */
final class StubRelationTable
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['columns' => []];
    }
}
```

- [ ] **Step 3: Write the failing test**

Create `packages/core/tests/Unit/Relations/RelationManagerTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;

it('derives a slug from the relationship name', function (): void {
    expect((new CommentsRelationManager)->slug())->toBe('comments');
});

it('detects hasMany relation type from the parent', function (): void {
    $type = (new CommentsRelationManager)->relationType(new RelPost);

    expect($type)->toBe('hasMany')
        ->and((new CommentsRelationManager)->supportsAttach(new RelPost))->toBeFalse();
});

it('exposes a table object and a null form by default', function (): void {
    $manager = new CommentsRelationManager;

    // Duck-typed: core does not depend on arqel-dev/table, so we assert the
    // shape (a toArray()-able object), not an instanceof Table.
    expect($manager->table())->toBeObject()
        ->and(method_exists($manager->table(), 'toArray'))->toBeTrue()
        ->and($manager->form())->toBeNull();
});
```

- [ ] **Step 4: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Unit/Relations/RelationManagerTest.php --no-coverage`
Expected: FAIL ("Class ... RelationManager not found").

- [ ] **Step 5: Implement `RelationManager`**

Create `packages/core/src/Relations/RelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Base class a consumer app extends to manage a parent record's Eloquent
 * relation from the parent's edit page.
 *
 * The child sets `$relationship`, declares `table()` (and optionally
 * `form()`), and the relation type (hasMany/morphMany/belongsToMany) is
 * detected at runtime from the parent's relation instance. MorphTo and
 * HasManyThrough are intentionally out of scope for 0.18.
 *
 * `table()`/`form()` return `mixed` (a Table-/Form-shaped object) rather
 * than concrete `Arqel\Table\Table`/`Arqel\Form\Form` types: `arqel-dev/core`
 * deliberately does not depend on `arqel-dev/table`/`arqel-dev/form` (they
 * depend on core — a hard type-hint would be a circular dependency). This
 * mirrors `Resource::table()`/`form()`, which are `mixed` for the same
 * documented reason. Consumers/serializers duck-type via `->toArray()`.
 */
abstract class RelationManager
{
    /** @var string Eloquent relation method name on the parent model. */
    public static string $relationship;

    abstract public function table(): mixed;

    /**
     * Field list for create/edit — the validation source, exactly like
     * `Resource::fields()`. The RelationController extracts rules from these
     * via the same string-referenced FieldRulesExtractor the ResourceController
     * uses, so `core` stays free of a hard dependency on arqel-dev/form.
     *
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [];
    }

    public function form(): mixed
    {
        return null;
    }

    /** @return class-string|null */
    public function relatedResource(): ?string
    {
        return null;
    }

    public function slug(): string
    {
        return Str::snake(static::$relationship);
    }

    /**
     * Detect the supported relation type from the parent's relation instance.
     *
     * @return 'hasMany'|'morphMany'|'belongsToMany'
     */
    public function relationType(Model $parent): string
    {
        $relation = $parent->{static::$relationship}();

        return match (true) {
            $relation instanceof MorphMany => 'morphMany',
            $relation instanceof BelongsToMany => 'belongsToMany',
            $relation instanceof HasMany => 'hasMany',
            default => throw new InvalidArgumentException(sprintf(
                'Relation [%s] on [%s] is of an unsupported type for a RelationManager (only hasMany/morphMany/belongsToMany).',
                static::$relationship,
                $parent::class,
            )),
        };
    }

    public function supportsAttach(Model $parent): bool
    {
        return $this->relationType($parent) === 'belongsToMany';
    }
}
```

Note: `MorphMany extends HasMany` in Laravel, so the `match` checks `MorphMany` before `HasMany` — order matters.

- [ ] **Step 6: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Unit/Relations/RelationManagerTest.php --no-coverage`
Expected: PASS (3 tests).

- [ ] **Step 7: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core/src/Relations packages/core/tests/Unit/Relations packages/core/tests/Fixtures --test
git add packages/core/src/Relations packages/core/tests/Unit/Relations packages/core/tests/Fixtures/Relations packages/core/tests/Fixtures/Models/Rel*.php
git commit --no-verify --signoff -m "feat(core): add RelationManager base class + relation-type detection

Implements Task 1 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 2: `Resource::relations()` extension point

Adds the additive hook on Resource so a Resource can declare its RelationManagers.

**Files:**
- Modify: `packages/core/src/Resources/Resource.php` (add `relations()` + `getRelations()` near `table()`/`form()`, ~line 184-203)
- Test: `packages/core/tests/Unit/Resources/ResourceRelationsTest.php`
- Test fixture: `packages/core/tests/Fixtures/Resources/RelPostResource.php`

**Interfaces:**
- Consumes: `Arqel\Core\Relations\RelationManager` (Task 1).
- Produces: on `Resource`:
  - `public function relations(): array { return []; }` — returns array of `class-string<RelationManager>`.
  - `public function getRelations(): array` — instantiates each, keyed by `slug()`; throws if a class is not a `RelationManager`.

- [ ] **Step 1: Create the Resource fixture**

Create `packages/core/tests/Fixtures/Resources/RelPostResource.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Resources;

use Arqel\Core\Resources\Resource;
use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;

final class RelPostResource extends Resource
{
    public static string $model = RelPost::class;

    public function fields(): array
    {
        return [];
    }

    public function relations(): array
    {
        return [CommentsRelationManager::class];
    }
}
```

Note: confirm `Resource`'s abstract surface — if `Resource` requires more than `$model` + `fields()` (e.g. a slug), mirror an existing minimal fixture Resource in `packages/core/tests/Fixtures/Resources/` and adapt. Do not invent required members.

- [ ] **Step 2: Write the failing test**

Create `packages/core/tests/Unit/Resources/ResourceRelationsTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Relations\RelationManager;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;
use Arqel\Core\Tests\Fixtures\Resources\RelPostResource;

it('returns an empty relations array by default', function (): void {
    $resource = new class extends \Arqel\Core\Resources\Resource {
        public static string $model = \Arqel\Core\Tests\Fixtures\Models\RelComment::class;
        public function fields(): array { return []; }
    };

    expect($resource->relations())->toBe([])
        ->and($resource->getRelations())->toBe([]);
});

it('instantiates declared relation managers keyed by slug', function (): void {
    $managers = (new RelPostResource)->getRelations();

    expect($managers)->toHaveKey('comments')
        ->and($managers['comments'])->toBeInstanceOf(CommentsRelationManager::class)
        ->and($managers['comments'])->toBeInstanceOf(RelationManager::class);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Unit/Resources/ResourceRelationsTest.php --no-coverage`
Expected: FAIL ("Call to undefined method ... getRelations()").

- [ ] **Step 4: Implement on Resource**

In `packages/core/src/Resources/Resource.php`, add after `form()` (around line 203), plus the import at the top:

```php
use Arqel\Core\Relations\RelationManager;
```

```php
    /**
     * Relation managers declared for this Resource.
     *
     * @return array<int, class-string<RelationManager>>
     */
    public function relations(): array
    {
        return [];
    }

    /**
     * Instantiate the declared relation managers, keyed by slug.
     *
     * @return array<string, RelationManager>
     */
    public function getRelations(): array
    {
        $managers = [];
        foreach ($this->relations() as $class) {
            $manager = new $class;
            if (! $manager instanceof RelationManager) {
                throw new \InvalidArgumentException(sprintf('[%s] must extend %s.', $class, RelationManager::class));
            }
            $managers[$manager->slug()] = $manager;
        }

        return $managers;
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Unit/Resources/ResourceRelationsTest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 6: Full core suite (no regression) + lint + commit**

Run: `cd packages/core && vendor/bin/pest --no-coverage` — expect all pass.

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core/src/Resources/Resource.php packages/core/tests/Unit/Resources/ResourceRelationsTest.php packages/core/tests/Fixtures/Resources/RelPostResource.php --test
git add packages/core/src/Resources/Resource.php packages/core/tests/Unit/Resources/ResourceRelationsTest.php packages/core/tests/Fixtures/Resources/RelPostResource.php
git commit --no-verify --signoff -m "feat(core): add Resource::relations() extension point

Implements Task 2 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 3: `RelationManager::toArray()` serialization

Serializes a manager (slug, label, type, table/form schema, per-user abilities) for Inertia props. Abilities are computed server-side against the related model's Policy.

**Files:**
- Modify: `packages/core/src/Relations/RelationManager.php` (add `label()`, `abilities()`, `toArray()`)
- Test: `packages/core/tests/Unit/Relations/RelationManagerSerializationTest.php`

**Interfaces:**
- Consumes: `RelationManager` (Task 1), `Table::toArray()`, `Form::toArray()` (existing).
- Produces:
  - `public function label(): string` — defaults to a title-cased slug.
  - `public function abilities(Model $parent, ?Authenticatable $user): array` — `['create'=>bool,'update'=>bool,'delete'=>bool,'attach'=>bool,'detach'=>bool]`, each via `Gate::forUser($user)->allows($ability, $relatedModelClass)`; **fail-open** (true) when no Policy is registered for the related model, matching `ResourceController::authorize()`. `attach`/`detach` only ever true for belongsToMany.
  - `public function toArray(Model $parent, ?Authenticatable $user = null): array` — `['slug','label','type','table','fields','abilities']` where `table`=`$this->table()->toArray()` (duck-typed), and `fields`=serialized field schema for the create/edit modal via `Arqel\Core\Support\FieldSchemaSerializer::serialize($this->fields(), ...)` (the same serializer `ResourceController` uses — lives in `core`, no external dep). `fields` is `[]` when the manager declares none.

- [ ] **Step 1: Write the failing test**

Create `packages/core/tests/Unit/Relations/RelationManagerSerializationTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Relations\CommentsRelationManager;

it('serializes slug, label, type, table schema and abilities', function (): void {
    $array = (new CommentsRelationManager)->toArray(new RelPost, null);

    expect($array['slug'])->toBe('comments')
        ->and($array['label'])->toBe('Comments')
        ->and($array['type'])->toBe('hasMany')
        ->and($array['table'])->toBeArray()
        ->and($array['fields'])->toBeArray()
        ->and($array['abilities'])->toHaveKeys(['create', 'update', 'delete', 'attach', 'detach']);
});

it('fails open on abilities when no policy is registered', function (): void {
    $abilities = (new CommentsRelationManager)->abilities(new RelPost, null);

    expect($abilities['create'])->toBeTrue()
        ->and($abilities['update'])->toBeTrue();
});

it('never grants attach/detach for a non-belongsToMany relation', function (): void {
    $abilities = (new CommentsRelationManager)->abilities(new RelPost, null);

    expect($abilities['attach'])->toBeFalse()
        ->and($abilities['detach'])->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Unit/Relations/RelationManagerSerializationTest.php --no-coverage`
Expected: FAIL ("Call to undefined method ... toArray()").

- [ ] **Step 3: Implement serialization on RelationManager**

Add to `packages/core/src/Relations/RelationManager.php` (with imports `use Illuminate\Contracts\Auth\Authenticatable;`, `use Illuminate\Support\Facades\Gate;`):

```php
    public function label(): string
    {
        return \Illuminate\Support\Str::headline($this->slug());
    }

    /**
     * Compute the current user's abilities on the related model, gated by
     * the related model's Policy. Fails open (true) when no Policy exists,
     * matching ResourceController::authorize() semantics.
     *
     * @return array<string, bool>
     */
    public function abilities(Model $parent, ?Authenticatable $user): array
    {
        $related = $parent->{static::$relationship}()->getRelated();
        $relatedClass = $related::class;
        $canAttach = $this->supportsAttach($parent);

        $check = function (string $ability) use ($user, $relatedClass): bool {
            if (Gate::getPolicyFor($relatedClass) === null) {
                return true; // fail-open: no policy registered
            }

            return Gate::forUser($user)->allows($ability, $relatedClass);
        };

        return [
            'create' => $check('create'),
            'update' => $check('update'),
            'delete' => $check('delete'),
            'attach' => $canAttach && $check('attach'),
            'detach' => $canAttach && $check('detach'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Model $parent, ?Authenticatable $user = null): array
    {
        $table = $this->table();

        return [
            'slug' => $this->slug(),
            'label' => $this->label(),
            'type' => $this->relationType($parent),
            'table' => method_exists($table, 'toArray') ? $table->toArray() : [],
            'fields' => app(\Arqel\Core\Support\FieldSchemaSerializer::class)->serialize($this->fields(), null, $user),
            'abilities' => $this->abilities($parent, $user),
        ];
    }
```

Note: confirm `FieldSchemaSerializer::serialize()`'s exact signature — it is `serialize(array $fields, ?Model $record = null, ?Authenticatable $user = null, ?Model $owner = null, ?string $resourceSlug = null)`. Pass `$this->fields()` and the user; the record is null for a create schema (edit passes the related record in Task 6). When `fields()` is empty, `serialize([])` returns `[]`. This is the SAME serializer the Resource edit page uses, so the React `FormRenderer` consumes an identical schema shape.

Note: verify `Gate::getPolicyFor()` exists in the Laravel version (it does, on the Gate contract). If `attach`/`detach` abilities are not defined on a Policy but the Policy exists, `allows('attach', ...)` returns false — the plan's fallback to `create`/`delete` (spec §4) is applied at the controller layer (Task 6/7), not here; `abilities()` reports the literal `attach`/`detach` gate result for the UI.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Unit/Relations/RelationManagerSerializationTest.php --no-coverage`
Expected: PASS (3 tests).

- [ ] **Step 5: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core/src/Relations/RelationManager.php packages/core/tests/Unit/Relations/RelationManagerSerializationTest.php --test
git add packages/core/src/Relations/RelationManager.php packages/core/tests/Unit/Relations/RelationManagerSerializationTest.php
git commit --no-verify --signoff -m "feat(core): serialize RelationManager (schema + server-computed abilities)

Implements Task 3 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 4: `RelationController` — index (list scoped to parent)

The first controller endpoint. Lists related records, scoped to the parent, authorized by `viewAny`. Establishes the resolve-manager + scope-to-parent + authorize spine every later endpoint reuses.

**Files:**
- Create: `packages/core/src/Http/Controllers/RelationController.php`
- Modify: `packages/core/routes/arqel.php` (add the `index` relation route)
- Test: `packages/core/tests/Feature/Relations/RelationIndexTest.php`
- Modify: `packages/core/tests/TestCase.php` (add rel_posts/rel_comments/rel_tags/rel_post_tag migrations + register RelPostResource)

**Interfaces:**
- Consumes: `RelPostResource` (Task 2), `RelationManager` (Task 3), `ResourceRegistry`, `TableQueryBuilder`.
- Produces: `final class Arqel\Core\Http\Controllers\RelationController` with `public function index(Request $request, string $resource, string|int $parent, string $relation)`. Route name `arqel.resources.relations.index`. Renders Inertia (or returns JSON in tests) with the paginated related records + the manager's table schema.

- [ ] **Step 1: Add migrations + resource registration to TestCase**

In `packages/core/tests/TestCase.php`, add to the schema setup (follow the file's existing `defineDatabaseMigrations()`/`getEnvironmentSetUp()` pattern; if none, mirror how another feature test defines tables):

```php
\Illuminate\Support\Facades\Schema::create('rel_posts', function ($t): void { $t->increments('id'); $t->string('title')->nullable(); });
\Illuminate\Support\Facades\Schema::create('rel_comments', function ($t): void { $t->increments('id'); $t->unsignedInteger('post_id'); $t->string('body')->nullable(); });
\Illuminate\Support\Facades\Schema::create('rel_tags', function ($t): void { $t->increments('id'); $t->string('name')->nullable(); });
\Illuminate\Support\Facades\Schema::create('rel_post_tag', function ($t): void { $t->unsignedInteger('post_id'); $t->unsignedInteger('tag_id'); });
```

And register `RelPostResource` into the `ResourceRegistry` in the test environment (mirror how existing feature tests register a Resource — check `ResourceController` feature tests for the exact registration call).

- [ ] **Step 2: Write the failing feature test**

Create `packages/core/tests/Feature/Relations/RelationIndexTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelComment;
use Arqel\Core\Tests\Fixtures\Models\RelPost;

it('lists only the parent record\'s related records', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $other = RelPost::create(['title' => 'B']);
    RelComment::create(['post_id' => $post->id, 'body' => 'mine']);
    RelComment::create(['post_id' => $other->id, 'body' => 'theirs']);

    $response = $this->getJson(route('arqel.resources.relations.index', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]));

    $response->assertOk();
    $data = $response->json('records') ?? $response->json('props.records') ?? [];
    expect(collect($data)->pluck('body')->all())->toContain('mine')
        ->and(collect($data)->pluck('body')->all())->not->toContain('theirs');
});

it('404s for a relation not in the resource allowlist', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $this->getJson(route('arqel.resources.relations.index', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'unknownrel',
    ]))->assertNotFound();
});
```

Note: the exact response shape (`records` vs Inertia `props`) depends on how `ResourceController::index` returns data — match that convention. Read `ResourceController::index` first and mirror its response contract; adjust the assertion to the real shape.

- [ ] **Step 3: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/RelationIndexTest.php --no-coverage`
Expected: FAIL (route not defined).

- [ ] **Step 4: Implement the controller spine + index**

Create `packages/core/src/Http/Controllers/RelationController.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Arqel\Core\Registry\ResourceRegistry;
use Arqel\Core\Relations\RelationManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generic controller for relation-scoped CRUD + attach/detach. Every
 * endpoint resolves the parent Resource + RelationManager, scopes the
 * query to the parent (anti-IDOR), and authorizes against the related
 * model's Policy (fail-open when none).
 */
final class RelationController
{
    public function __construct(private readonly ResourceRegistry $registry) {}

    public function index(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [$resourceInstance, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);

        $this->authorize('viewAny', $parentModel, $manager, null);

        $related = $parentModel->{$manager::$relationship}();
        // Reuse the existing table query pipeline against the relation query.
        $records = $related->get(); // MVP: full list; wire TableQueryBuilder pagination in Task 5.

        return response()->json([
            'records' => $records->toArray(),
            'table' => $manager->table()->toArray(),
            'abilities' => $manager->abilities($parentModel, $request->user()),
        ]);
    }

    /**
     * Resolve [resourceInstance, manager, parentModel] or abort 404.
     *
     * @return array{0: object, 1: RelationManager, 2: Model}
     */
    private function resolve(string $resource, string|int $parent, string $relation): array
    {
        $resourceClass = $this->registry->findBySlug($resource);
        abort_if($resourceClass === null, Response::HTTP_NOT_FOUND);

        $resourceInstance = new $resourceClass;
        $managers = $resourceInstance->getRelations();
        abort_unless(isset($managers[$relation]), Response::HTTP_NOT_FOUND);

        $manager = $managers[$relation];
        $model = $resourceClass::$model;
        $parentModel = $model::query()->findOrFail($parent);

        return [$resourceInstance, $manager, $parentModel];
    }

    /**
     * Gate an ability against the related model's Policy. Fail-open when no
     * Policy is registered (matches ResourceController::authorize()).
     */
    private function authorize(string $ability, Model $parentModel, RelationManager $manager, ?Model $related): void
    {
        $relatedClass = $parentModel->{$manager::$relationship}()->getRelated()::class;
        if (Gate::getPolicyFor($relatedClass) === null) {
            return; // fail-open
        }
        $target = $related ?? $relatedClass;
        abort_if(Gate::denies($ability, $target), Response::HTTP_FORBIDDEN);
    }
}
```

Note: confirm `ResourceRegistry::findBySlug()` exists (grep it). If the registry lookup method has a different name/signature, use the real one — the `ResourceController` already resolves a resource by slug; mirror that exact call. Confirm `ResourceRegistry` is the correct injected dependency.

- [ ] **Step 5: Register the index route**

In `packages/core/routes/arqel.php`, inside the `Route::name('arqel.resources.')` group, add:

```php
Route::get('{resource}/{parent}/relations/{relation}', [\Arqel\Core\Http\Controllers\RelationController::class, 'index'])
    ->name('relations.index')
    ->where('resource', $resourceSlugPattern);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/RelationIndexTest.php --no-coverage`
Expected: PASS (2 tests). Adjust the record-shape assertion to the real response contract if needed.

- [ ] **Step 7: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core/src/Http/Controllers/RelationController.php packages/core/tests --test
git add packages/core/src/Http/Controllers/RelationController.php packages/core/routes/arqel.php packages/core/tests/Feature/Relations packages/core/tests/TestCase.php
git commit --no-verify --signoff -m "feat(core): add RelationController::index (parent-scoped, authorized)

Implements Task 4 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 5: `RelationController` — create + store (HasMany/MorphMany)

Adds create (form schema for the modal) and store (persist a child, FK/morph injected). HasMany/MorphMany path.

**Files:**
- Modify: `packages/core/src/Http/Controllers/RelationController.php` (add `create`, `store`)
- Modify: `packages/core/routes/arqel.php` (create + store routes)
- Modify: `packages/core/tests/Fixtures/Relations/CommentsRelationManager.php` (add a `fields()` returning a field with a `body` text field, `required`)
- Test: `packages/core/tests/Feature/Relations/RelationStoreTest.php`

**Interfaces:**
- Consumes: `resolve()`/`authorize()` (Task 4), `RelationManager::fields()` (Task 1).
- Produces: `create(...)` → route `arqel.resources.relations.create` (GET), returns serialized field schema; `store(...)` → route `arqel.resources.relations.store` (POST), validates via the field-derived rules, creates via `$parent->{relation}()->create($validated)`, returns redirect. Authorizes `create`. Adds `private rulesFromFields(RelationManager $manager): array` mirroring `ResourceController::extractRules()` exactly (string-referenced `Arqel\Form\FieldRulesExtractor` + `class_exists` guard, over `$manager->fields()`).

- [ ] **Step 1: Add fields to the fixture manager**

Modify `packages/core/tests/Fixtures/Relations/CommentsRelationManager.php` to add a `fields()` returning a `body` text field marked required. **Mirror an existing Resource's `fields()` exactly** — read a real Resource fixture (or showcase Resource) to copy the field-builder API verbatim (the codebase convention is `use Arqel\Fields\FieldFactory as Field;` then `Field::text('body')->required()`). The fixture lives in `tests/`, so it MAY use `arqel-dev/fields`/`arqel-dev/form` if they are available to the core test suite; **confirm whether core's tests can resolve `Arqel\Fields\FieldFactory`** — if NOT (core stays dependency-free even in tests), instead return a minimal duck-typed field stub that the real `FieldRulesExtractor` can consume, OR (simpler) have the fixture's `fields()` return the already-extracted rule shape the test needs. Read how existing core feature tests that touch fields/validation set this up (e.g. any test asserting `assertSessionHasErrors`) and mirror that exact approach. Do NOT invent a field-builder API that core tests can't load.

Note: the goal of this fixture is only to make `store` validation testable. Whatever minimal, real mechanism the core test suite already uses to get a `required` rule onto a field is the one to copy.

- [ ] **Step 2: Write the failing store test**

Create `packages/core/tests/Feature/Relations/RelationStoreTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelComment;
use Arqel\Core\Tests\Fixtures\Models\RelPost;

it('stores a child record with the parent FK injected', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $this->post(route('arqel.resources.relations.store', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]), ['body' => 'hello'])->assertRedirect();

    expect(RelComment::where('post_id', $post->id)->where('body', 'hello')->exists())->toBeTrue();
});

it('rejects an invalid child (missing required field)', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $this->post(route('arqel.resources.relations.store', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]), ['body' => ''])->assertSessionHasErrors('body');

    expect(RelComment::where('post_id', $post->id)->count())->toBe(0);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/RelationStoreTest.php --no-coverage`
Expected: FAIL (route not defined).

- [ ] **Step 4: Implement create + store**

Add to `RelationController`:

```php
    public function create(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $this->authorize('create', $parentModel, $manager, null);

        return response()->json([
            'fields' => app(\Arqel\Core\Support\FieldSchemaSerializer::class)->serialize($manager->fields(), null, $request->user()),
        ]);
    }

    public function store(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $this->authorize('create', $parentModel, $manager, null);

        $validated = $request->validate($this->rulesFromFields($manager));

        $parentModel->{$manager::$relationship}()->create($validated);

        return back()->with('success', 'arqel::relations.created');
    }

    /**
     * Extract validation rules from the manager's fields via the SAME
     * string-referenced FieldRulesExtractor that ResourceController::extractRules()
     * uses — keeps `core` free of a hard dependency on arqel-dev/form.
     *
     * @return array<string, mixed>
     */
    private function rulesFromFields(RelationManager $manager): array
    {
        $extractorClass = 'Arqel\\Form\\FieldRulesExtractor';
        if (! class_exists($extractorClass)) {
            return [];
        }

        $extractor = (new \ReflectionClass($extractorClass))->newInstance();
        if (! method_exists($extractor, 'extract')) {
            return [];
        }

        $rules = $extractor->extract($manager->fields());
        if (! is_array($rules)) {
            return [];
        }

        $clean = [];
        foreach ($rules as $name => $set) {
            if (is_string($name) && is_array($set)) {
                $clean[$name] = $set;
            }
        }

        return $clean;
    }
```

Note: this `rulesFromFields()` is a faithful copy of `ResourceController::extractRules()` (packages/core/src/Http/Controllers/ResourceController.php:1039) minus the fail-closed logging (a relation form is optional, so an absent extractor → no rules is acceptable here, matching a manager that declares no fields). Read `extractRules()` and keep the two consistent. Do NOT invent a `FieldRulesExtractor::fromForm()` — the real API is an instance `->extract(array $fields)`.

- [ ] **Step 5: Register create + store routes**

In `packages/core/routes/arqel.php`, in the same group:

```php
Route::get('{resource}/{parent}/relations/{relation}/create', [\Arqel\Core\Http\Controllers\RelationController::class, 'create'])
    ->name('relations.create')->where('resource', $resourceSlugPattern);
Route::post('{resource}/{parent}/relations/{relation}', [\Arqel\Core\Http\Controllers\RelationController::class, 'store'])
    ->name('relations.store')->where('resource', $resourceSlugPattern);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/RelationStoreTest.php --no-coverage`
Expected: PASS (2 tests).

- [ ] **Step 7: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core/src/Http/Controllers/RelationController.php packages/core/tests --test
git add packages/core/src/Http/Controllers/RelationController.php packages/core/routes/arqel.php packages/core/tests
git commit --no-verify --signoff -m "feat(core): add RelationController create + store (hasMany/morphMany)

Implements Task 5 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 6: `RelationController` — edit + update + destroy

Completes CRUD for the child record, all scoped to the parent.

**Files:**
- Modify: `packages/core/src/Http/Controllers/RelationController.php` (add `edit`, `update`, `destroy`)
- Modify: `packages/core/routes/arqel.php` (edit/update/destroy routes)
- Test: `packages/core/tests/Feature/Relations/RelationUpdateDestroyTest.php`

**Interfaces:**
- Consumes: `resolve()`/`authorize()`/`rulesFromFields()` (Tasks 4-5).
- Produces: `edit`/`update`/`destroy`, routes `arqel.resources.relations.{edit,update,destroy}`. Each resolves the related record via `$parent->{relation}()->findOrFail($related)` (anti-IDOR). Authorizes `update`/`update`/`delete`.

- [ ] **Step 1: Write the failing test**

Create `packages/core/tests/Feature/Relations/RelationUpdateDestroyTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelComment;
use Arqel\Core\Tests\Fixtures\Models\RelPost;

it('updates a related record scoped to its parent', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $comment = RelComment::create(['post_id' => $post->id, 'body' => 'old']);

    $this->put(route('arqel.resources.relations.update', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $comment->id,
    ]), ['body' => 'new'])->assertRedirect();

    expect($comment->fresh()->body)->toBe('new');
});

it('404s when updating a related record belonging to another parent', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $other = RelPost::create(['title' => 'B']);
    $foreign = RelComment::create(['post_id' => $other->id, 'body' => 'x']);

    $this->put(route('arqel.resources.relations.update', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $foreign->id,
    ]), ['body' => 'hack'])->assertNotFound();
});

it('destroys a related record scoped to its parent', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $comment = RelComment::create(['post_id' => $post->id, 'body' => 'z']);

    $this->delete(route('arqel.resources.relations.destroy', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments', 'related' => $comment->id,
    ]))->assertRedirect();

    expect(RelComment::find($comment->id))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/RelationUpdateDestroyTest.php --no-coverage`
Expected: FAIL (routes not defined).

- [ ] **Step 3: Implement edit + update + destroy**

Add to `RelationController` (add a private helper to resolve the related record scoped to the parent):

```php
    public function edit(Request $request, string $resource, string|int $parent, string $relation, string|int $related): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $record = $this->findRelated($parentModel, $manager, $related);
        $this->authorize('update', $parentModel, $manager, $record);

        return response()->json([
            'fields' => app(\Arqel\Core\Support\FieldSchemaSerializer::class)->serialize($manager->fields(), $record, $request->user()),
            'record' => $record->toArray(),
        ]);
    }

    public function update(Request $request, string $resource, string|int $parent, string $relation, string|int $related): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $record = $this->findRelated($parentModel, $manager, $related);
        $this->authorize('update', $parentModel, $manager, $record);

        $validated = $request->validate($this->rulesFromFields($manager));
        $record->update($validated);

        return back()->with('success', 'arqel::relations.updated');
    }

    public function destroy(Request $request, string $resource, string|int $parent, string $relation, string|int $related): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        $record = $this->findRelated($parentModel, $manager, $related);
        $this->authorize('delete', $parentModel, $manager, $record);

        $record->delete();

        return back()->with('success', 'arqel::relations.deleted');
    }

    private function findRelated(Model $parentModel, RelationManager $manager, string|int $related): Model
    {
        // Scoped to the parent's relation → a related id from another parent 404s.
        return $parentModel->{$manager::$relationship}()->findOrFail($related);
    }
```

- [ ] **Step 4: Register the routes**

```php
Route::get('{resource}/{parent}/relations/{relation}/{related}/edit', [\Arqel\Core\Http\Controllers\RelationController::class, 'edit'])
    ->name('relations.edit')->where('resource', $resourceSlugPattern);
Route::put('{resource}/{parent}/relations/{relation}/{related}', [\Arqel\Core\Http\Controllers\RelationController::class, 'update'])
    ->name('relations.update')->where('resource', $resourceSlugPattern);
Route::delete('{resource}/{parent}/relations/{relation}/{related}', [\Arqel\Core\Http\Controllers\RelationController::class, 'destroy'])
    ->name('relations.destroy')->where('resource', $resourceSlugPattern);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/RelationUpdateDestroyTest.php --no-coverage`
Expected: PASS (3 tests).

- [ ] **Step 6: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core/src/Http/Controllers/RelationController.php packages/core/tests --test
git add packages/core/src/Http/Controllers/RelationController.php packages/core/routes/arqel.php packages/core/tests
git commit --no-verify --signoff -m "feat(core): add RelationController edit/update/destroy (parent-scoped)

Implements Task 6 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 7: `RelationController` — attach + detach (BelongsToMany) + 405 guard

Adds pivot attach/detach for BelongsToMany, and rejects attach/detach on non-belongsToMany relations with 405.

**Files:**
- Modify: `packages/core/src/Http/Controllers/RelationController.php` (add `attach`, `detach`)
- Modify: `packages/core/routes/arqel.php` (attach/detach routes)
- Modify: `packages/core/tests/Fixtures/Resources/RelPostResource.php` (add a `TagsRelationManager`) + create `packages/core/tests/Fixtures/Relations/TagsRelationManager.php`
- Test: `packages/core/tests/Feature/Relations/RelationAttachDetachTest.php`

**Interfaces:**
- Consumes: `resolve()`/`authorize()` (Task 4), `supportsAttach()` (Task 1).
- Produces: `attach`/`detach`, routes `arqel.resources.relations.{attach,detach}`. `attach` → `$parent->{relation}()->attach($id, $pivot)`; `detach` → `$parent->{relation}()->detach($id)`. On a non-belongsToMany relation → abort 405. attach authorizes `attach` (fallback `create`); detach authorizes `detach` (fallback `delete`).

- [ ] **Step 1: Create the TagsRelationManager fixture + register it**

Create `packages/core/tests/Fixtures/Relations/TagsRelationManager.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Tests\Fixtures\Relations;

use Arqel\Core\Relations\RelationManager;
use Arqel\Core\Tests\Fixtures\Relations\StubRelationTable;

final class TagsRelationManager extends RelationManager
{
    public static string $relationship = 'tags';

    public function table(): mixed
    {
        return new StubRelationTable;
    }
}
```

Modify `RelPostResource::relations()` to `return [CommentsRelationManager::class, TagsRelationManager::class];` (add the import).

- [ ] **Step 2: Write the failing test**

Create `packages/core/tests/Feature/Relations/RelationAttachDetachTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelPost;
use Arqel\Core\Tests\Fixtures\Models\RelTag;

it('attaches an existing tag to the post via the pivot', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $tag = RelTag::create(['name' => 'php']);

    $this->post(route('arqel.resources.relations.attach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'tags',
    ]), ['related' => $tag->id])->assertRedirect();

    expect($post->tags()->whereKey($tag->id)->exists())->toBeTrue();
});

it('detaches without deleting the tag record', function (): void {
    $post = RelPost::create(['title' => 'A']);
    $tag = RelTag::create(['name' => 'php']);
    $post->tags()->attach($tag->id);

    $this->delete(route('arqel.resources.relations.detach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'tags', 'related' => $tag->id,
    ]))->assertRedirect();

    expect($post->tags()->whereKey($tag->id)->exists())->toBeFalse()
        ->and(RelTag::find($tag->id))->not->toBeNull(); // record survives
});

it('405s when attaching on a hasMany relation', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $this->post(route('arqel.resources.relations.attach', [
        'resource' => 'rel-posts', 'parent' => $post->id, 'relation' => 'comments',
    ]), ['related' => 1])->assertStatus(405);
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/RelationAttachDetachTest.php --no-coverage`
Expected: FAIL (routes not defined).

- [ ] **Step 4: Implement attach + detach**

Add to `RelationController` (with `use Symfony\Component\HttpFoundation\Response;` already imported):

```php
    public function attach(Request $request, string $resource, string|int $parent, string $relation): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        abort_unless($manager->supportsAttach($parentModel), Response::HTTP_METHOD_NOT_ALLOWED);
        $this->authorizeAttach('attach', 'create', $parentModel, $manager);

        $validated = $request->validate(['related' => ['required']]);
        $pivot = $request->input('pivot', []);
        $parentModel->{$manager::$relationship}()->attach($validated['related'], is_array($pivot) ? $pivot : []);

        return back()->with('success', 'arqel::relations.attached');
    }

    public function detach(Request $request, string $resource, string|int $parent, string $relation, string|int $related): mixed
    {
        [, $manager, $parentModel] = $this->resolve($resource, $parent, $relation);
        abort_unless($manager->supportsAttach($parentModel), Response::HTTP_METHOD_NOT_ALLOWED);
        $this->authorizeAttach('detach', 'delete', $parentModel, $manager);

        $parentModel->{$manager::$relationship}()->detach($related);

        return back()->with('success', 'arqel::relations.detached');
    }

    /**
     * Attach/detach authz: try the bespoke ability first, fall back to the
     * CRUD ability, fail-open when no Policy exists.
     */
    private function authorizeAttach(string $ability, string $fallback, Model $parentModel, RelationManager $manager): void
    {
        $relatedClass = $parentModel->{$manager::$relationship}()->getRelated()::class;
        if (Gate::getPolicyFor($relatedClass) === null) {
            return; // fail-open
        }
        $allowed = Gate::allows($ability, $relatedClass) || Gate::allows($fallback, $relatedClass);
        abort_unless($allowed, Response::HTTP_FORBIDDEN);
    }
```

- [ ] **Step 5: Register attach + detach routes**

```php
Route::post('{resource}/{parent}/relations/{relation}/attach', [\Arqel\Core\Http\Controllers\RelationController::class, 'attach'])
    ->name('relations.attach')->where('resource', $resourceSlugPattern);
Route::delete('{resource}/{parent}/relations/{relation}/{related}/detach', [\Arqel\Core\Http\Controllers\RelationController::class, 'detach'])
    ->name('relations.detach')->where('resource', $resourceSlugPattern);
```

Note: register `relations.attach` (fixed segment `attach`) BEFORE `relations.update`/`destroy` (which use `{related}`) if there is any route-matching ambiguity — Laravel matches in registration order and `attach` is a literal segment. Verify no conflict; reorder if `attach` is captured as `{related}`.

- [ ] **Step 6: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/RelationAttachDetachTest.php --no-coverage`
Expected: PASS (3 tests).

- [ ] **Step 7: Full core suite + lint + commit**

Run: `cd packages/core && vendor/bin/pest --no-coverage` — expect all pass.

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core/src/Http/Controllers/RelationController.php packages/core/tests --test
git add packages/core/src/Http/Controllers/RelationController.php packages/core/routes/arqel.php packages/core/tests
git commit --no-verify --signoff -m "feat(core): add RelationController attach/detach (belongsToMany) + 405 guard

Implements Task 7 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 8: Wire relations into the edit-page Inertia props + i18n keys

The edit page must ship `relations` props so the React tabs can render. Adds the serialized managers to the Resource edit payload + the i18n keys used by controller flashes.

**Files:**
- Modify: `packages/core/src/Http/Controllers/ResourceController.php` (`edit()` — add `relations` to the Inertia props)
- Create: `packages/core/resources/lang/en/relations.php`, `packages/core/resources/lang/pt_BR/relations.php`
- Test: `packages/core/tests/Feature/Relations/EditPageRelationsPropTest.php`

**Interfaces:**
- Consumes: `Resource::getRelations()` (Task 2), `RelationManager::toArray()` (Task 3).
- Produces: the Resource `edit` Inertia response now includes `relations` = array of `toArray()` for each manager (empty array when none). i18n keys `arqel::relations.{created,updated,deleted,attached,detached}`.

- [ ] **Step 1: Add i18n files**

Create `packages/core/resources/lang/en/relations.php`:

```php
<?php

declare(strict_types=1);

return [
    'created' => 'Related record created.',
    'updated' => 'Related record updated.',
    'deleted' => 'Related record deleted.',
    'attached' => 'Record attached.',
    'detached' => 'Record detached.',
];
```

Create `packages/core/resources/lang/pt_BR/relations.php`:

```php
<?php

declare(strict_types=1);

return [
    'created' => 'Registro relacionado criado.',
    'updated' => 'Registro relacionado atualizado.',
    'deleted' => 'Registro relacionado excluído.',
    'attached' => 'Registro anexado.',
    'detached' => 'Registro desanexado.',
];
```

Note: confirm `packages/core` already registers a translation namespace `arqel::` (it ships other lang files). If the namespace prefix differs, match the existing one and update the controller flash keys in Tasks 5-7 accordingly.

- [ ] **Step 2: Write the failing test**

Create `packages/core/tests/Feature/Relations/EditPageRelationsPropTest.php`:

```php
<?php

declare(strict_types=1);

use Arqel\Core\Tests\Fixtures\Models\RelPost;

it('includes serialized relations in the edit page props', function (): void {
    $post = RelPost::create(['title' => 'A']);

    $response = $this->get(route('arqel.resources.edit', ['resource' => 'rel-posts', 'id' => $post->id]));

    $response->assertOk();
    // Inertia props: match how existing edit-page feature tests assert props
    // (e.g. $response->assertInertia or the JSON prop bag). Mirror that.
    $relations = data_get($response->viewData('page') ?? [], 'props.relations', null)
        ?? $response->json('props.relations');
    expect($relations)->toBeArray();
    expect(collect($relations)->pluck('slug')->all())->toContain('comments', 'tags');
});
```

Note: the assertion mechanism must match how the codebase tests Inertia props elsewhere (`assertInertia(fn (Assert $page) => ...)` is the idiomatic Inertia testing helper). Read an existing edit-page feature test and mirror its exact prop-assertion style.

- [ ] **Step 3: Run test to verify it fails**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/EditPageRelationsPropTest.php --no-coverage`
Expected: FAIL (`relations` prop absent).

- [ ] **Step 4: Add relations to the edit props**

In `ResourceController::edit()`, where the Inertia props array is built, add:

```php
'relations' => collect($resourceInstance->getRelations())
    ->map(fn ($manager) => $manager->toArray($record, $request->user()))
    ->values()
    ->all(),
```

Note: use the real variable names present in `edit()` (`$record`, the resource instance, `$request`). Read the method first and insert into its existing props array — do not restructure the method.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd packages/core && vendor/bin/pest tests/Feature/Relations/EditPageRelationsPropTest.php --no-coverage`
Expected: PASS.

- [ ] **Step 6: Full core suite + lint + commit**

Run: `cd packages/core && vendor/bin/pest --no-coverage` — expect all pass (zero regression: Resources without relations get `relations => []`).

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core/src/Http/Controllers/ResourceController.php packages/core/tests --test
git add packages/core/src/Http/Controllers/ResourceController.php packages/core/resources/lang packages/core/tests
git commit --no-verify --signoff -m "feat(core): ship serialized relations in Resource edit props + i18n

Implements Task 8 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 9: React types + `RelationManagerPanel` (list + row actions)

The first React unit: types for the serialized relation + a panel rendering the related-records DataTable with ability-gated toolbar/row actions. (Modal wiring in Task 10; tabs wrapper in Task 11.)

**Files:**
- Modify: `packages-js/types/src/*` (add `RelationManagerProps` type) — confirm exact file (e.g. `inertia.ts`)
- Create: `packages-js/ui/src/relations/RelationManagerPanel.tsx`
- Test: `packages-js/ui/tests/relations/RelationManagerPanel.test.tsx`

**Interfaces:**
- Consumes: existing `DataTable`, `TableToolbar` from `packages-js/ui/src/table/*`; `router` from `@inertiajs/react`.
- Produces: `interface RelationManagerProps { slug: string; label: string; type: 'hasMany'|'morphMany'|'belongsToMany'; table: unknown; fields: unknown[]; abilities: { create: boolean; update: boolean; delete: boolean; attach: boolean; detach: boolean } }`; `export function RelationManagerPanel(props: { relation: RelationManagerProps; parentSlug: string; parentId: string|number; records: unknown[]; onEdit(id): void; onCreate(): void; onAttach(): void })`.

- [ ] **Step 1: Add the type**

In the types package (confirm the file — likely `packages-js/types/src/inertia.ts` or a new `relations.ts`), add:

```ts
export interface RelationManagerAbilities {
  create: boolean;
  update: boolean;
  delete: boolean;
  attach: boolean;
  detach: boolean;
}

export interface RelationManagerProps {
  slug: string;
  label: string;
  type: 'hasMany' | 'morphMany' | 'belongsToMany';
  table: unknown;
  fields: unknown[];
  abilities: RelationManagerAbilities;
}
```

Export it from the package index. Then rebuild the types package so `@arqel-dev/types` resolves (per the toolchain note: build order types → hooks → react).

- [ ] **Step 2: Write the failing test**

Create `packages-js/ui/tests/relations/RelationManagerPanel.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn(), delete: vi.fn() } }));
vi.mock('../../src/table/DataTable.js', () => ({
  DataTable: () => <div data-testid="data-table" />,
}));

import { RelationManagerPanel } from '../../src/relations/RelationManagerPanel.js';

const base = {
  slug: 'comments', label: 'Comments', type: 'hasMany' as const,
  table: {}, fields: [], abilities: { create: true, update: true, delete: true, attach: false, detach: false },
};

describe('RelationManagerPanel', () => {
  it('renders the data table and a New button when create is allowed', () => {
    render(<RelationManagerPanel relation={base} parentSlug="rel-posts" parentId={1} records={[]} onEdit={() => {}} onCreate={() => {}} onAttach={() => {}} />);
    expect(screen.getByTestId('data-table')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /new/i })).toBeInTheDocument();
  });

  it('hides the New button when create is not allowed', () => {
    render(<RelationManagerPanel relation={{ ...base, abilities: { ...base.abilities, create: false } }} parentSlug="rel-posts" parentId={1} records={[]} onEdit={() => {}} onCreate={() => {}} onAttach={() => {}} />);
    expect(screen.queryByRole('button', { name: /new/i })).toBeNull();
  });

  it('shows an Attach button only for belongsToMany with attach allowed', () => {
    render(<RelationManagerPanel relation={{ ...base, type: 'belongsToMany', abilities: { ...base.abilities, attach: true } }} parentSlug="rel-posts" parentId={1} records={[]} onEdit={() => {}} onCreate={() => {}} onAttach={() => {}} />);
    expect(screen.getByRole('button', { name: /attach/i })).toBeInTheDocument();
  });
});
```

- [ ] **Step 3: Run test to verify it fails**

Run: `pnpm --filter @arqel-dev/ui test relations/RelationManagerPanel --run`
Expected: FAIL ("Cannot find module ... RelationManagerPanel").

- [ ] **Step 4: Implement `RelationManagerPanel`**

Create `packages-js/ui/src/relations/RelationManagerPanel.tsx`:

```tsx
import type { RelationManagerProps } from '@arqel-dev/types';
import { DataTable } from '../table/DataTable.js';
import { Button } from '../shadcn/ui/button.js';

export interface RelationManagerPanelProps {
  relation: RelationManagerProps;
  parentSlug: string;
  parentId: string | number;
  records: unknown[];
  onEdit(id: string | number): void;
  onCreate(): void;
  onAttach(): void;
}

/**
 * One relation-manager tab: a DataTable of related records with
 * ability-gated toolbar (New / Attach) and per-row actions. Presentational —
 * mutation is delegated to the parent page via the callbacks.
 */
export function RelationManagerPanel({ relation, records, onCreate, onAttach }: RelationManagerPanelProps) {
  const { abilities, type } = relation;

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-end gap-2">
        {abilities.create && (
          <Button type="button" size="sm" onClick={onCreate}>
            New
          </Button>
        )}
        {type === 'belongsToMany' && abilities.attach && (
          <Button type="button" size="sm" variant="secondary" onClick={onAttach}>
            Attach
          </Button>
        )}
      </div>
      <DataTable /* wire schema/records per the real DataTable prop contract */ />
    </div>
  );
}
```

Note: read the real `DataTable` prop contract and `Button` import path before finalizing — pass the relation's `table` schema + `records` the way `ArqelIndexPage` feeds `DataTable`. Match reality; do not invent DataTable props. Labels ("New"/"Attach") should use `useArqelTranslations` with the `arqel::relations.*` keys once wired — for this task a literal fallback is acceptable if the surrounding components do the same.

- [ ] **Step 5: Run test to verify it passes**

Run: `pnpm --filter @arqel-dev/ui test relations/RelationManagerPanel --run`
Expected: PASS (3 tests).

- [ ] **Step 6: Typecheck + lint + commit**

```bash
pnpm --filter @arqel-dev/ui typecheck
pnpm lint
git add packages-js/types/src packages-js/ui/src/relations/RelationManagerPanel.tsx packages-js/ui/tests/relations
git commit --no-verify --signoff -m "feat(ui): add RelationManagerPanel + RelationManagerProps type

Implements Task 9 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 10: `RelationFormModal` + `AttachModal` (Inertia mutation)

The modals that create/edit a child (reusing FormRenderer) and attach an existing record, submitting via Inertia with a partial reload.

**Files:**
- Create: `packages-js/ui/src/relations/RelationFormModal.tsx`
- Create: `packages-js/ui/src/relations/AttachModal.tsx`
- Test: `packages-js/ui/tests/relations/RelationFormModal.test.tsx`

**Interfaces:**
- Consumes: existing `Modal`, `FormRenderer` (`packages-js/ui/src/...`), `BelongsToInput` (`packages-js/fields-js/...`), `router` from `@inertiajs/react`.
- Produces:
  - `RelationFormModal(props: { open: boolean; onClose(): void; relation: RelationManagerProps; parentSlug: string; parentId: string|number; recordId?: string|number })` — GETs create/edit schema, submits store/update, on success closes + `router.reload({ only: ['relations'] })`.
  - `AttachModal(props: { open; onClose; relation; parentSlug; parentId })` — a picker submitting to attach.

- [ ] **Step 1: Write the failing test**

Create `packages-js/ui/tests/relations/RelationFormModal.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();
const reload = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { post: (...a: unknown[]) => post(...a), reload: (...a: unknown[]) => reload(...a) } }));
vi.mock('../../src/form/FormRenderer.js', () => ({
  FormRenderer: ({ onSubmit }: { onSubmit: () => void }) => (
    <button type="button" onClick={onSubmit}>submit-form</button>
  ),
}));

import { RelationFormModal } from '../../src/relations/RelationFormModal.js';

const relation = { slug: 'comments', label: 'Comments', type: 'hasMany' as const, table: {}, fields: [], abilities: { create: true, update: true, delete: true, attach: false, detach: false } };

describe('RelationFormModal', () => {
  it('posts to the relation store route and reloads only relations on success', async () => {
    render(<RelationFormModal open onClose={() => {}} relation={relation} parentSlug="rel-posts" parentId={1} />);
    await userEvent.click(screen.getByText('submit-form'));
    expect(post).toHaveBeenCalled();
    const opts = post.mock.calls[0][2] ?? post.mock.calls[0][1];
    // onSuccess should trigger a partial reload limited to 'relations'
    expect(typeof opts.onSuccess).toBe('function');
  });

  it('does not render when closed', () => {
    render(<RelationFormModal open={false} onClose={() => {}} relation={relation} parentSlug="rel-posts" parentId={1} />);
    expect(screen.queryByText('submit-form')).toBeNull();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm --filter @arqel-dev/ui test relations/RelationFormModal --run`
Expected: FAIL ("Cannot find module ... RelationFormModal").

- [ ] **Step 3: Implement the modals**

Create `packages-js/ui/src/relations/RelationFormModal.tsx` (reusing the real `Modal`/`FormRenderer` — confirm their prop contracts and import paths first):

```tsx
import type { RelationManagerProps } from '@arqel-dev/types';
import { router } from '@inertiajs/react';
import { Modal } from '../shadcn/ui/dialog.js'; // confirm the real Modal/Dialog path
import { FormRenderer } from '../form/FormRenderer.js';

export interface RelationFormModalProps {
  open: boolean;
  onClose(): void;
  relation: RelationManagerProps;
  parentSlug: string;
  parentId: string | number;
  recordId?: string | number;
}

export function RelationFormModal({ open, onClose, relation, parentSlug, parentId, recordId }: RelationFormModalProps) {
  if (!open) return null;

  const base = `/${parentSlug}/${parentId}/relations/${relation.slug}`;
  const url = recordId ? `${base}/${recordId}` : base;
  const method = recordId ? 'put' : 'post';

  const submit = (data: Record<string, unknown>) => {
    router[method](url, data, {
      preserveScroll: true,
      onSuccess: () => {
        router.reload({ only: ['relations'] });
        onClose();
      },
    });
  };

  return (
    <Modal open={open} onClose={onClose} title={relation.label}>
      <FormRenderer schema={relation.fields} onSubmit={submit} />
    </Modal>
  );
}
```

Create `packages-js/ui/src/relations/AttachModal.tsx` analogously — a `BelongsToInput`-style picker whose submit does `router.post(`${base}/attach`, { related: id, pivot }, { preserveScroll, onSuccess: reload+close })`. (Show the full component; mirror `BelongsToInput`'s real props.)

Note: confirm real import paths/props for `Modal`, `FormRenderer`, `BelongsToInput`. The admin app basePath prefix (`/admin`) may need to be prepended — check how `ArqelEditPage` builds its PUT url (it uses `props.basePath`) and mirror that exactly rather than hardcoding `/`.

- [ ] **Step 4: Run test to verify it passes**

Run: `pnpm --filter @arqel-dev/ui test relations/RelationFormModal --run`
Expected: PASS (2 tests).

- [ ] **Step 5: Typecheck + lint + commit**

```bash
pnpm --filter @arqel-dev/ui typecheck
pnpm lint
git add packages-js/ui/src/relations packages-js/ui/tests/relations/RelationFormModal.test.tsx
git commit --no-verify --signoff -m "feat(ui): add RelationFormModal + AttachModal (Inertia partial reload)

Implements Task 10 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 11: `ResourceEditTabs` — page-level tabs (form + relation panels), zero-regression

Wraps the edit page: a "Dados" tab (existing form) + one tab per relation. When there are no relations, renders the form exactly as before.

**Files:**
- Create: `packages-js/ui/src/relations/ResourceEditTabs.tsx`
- Modify: `packages-js/ui/src/pages/ArqelEditPage.tsx` (delegate to `ResourceEditTabs` when `relations` present)
- Test: `packages-js/ui/tests/relations/ResourceEditTabs.test.tsx`

**Interfaces:**
- Consumes: `RelationManagerPanel` (Task 9), `RelationFormModal`/`AttachModal` (Task 10), shadcn `Tabs`.
- Produces: `ResourceEditTabs(props: { relations: RelationManagerProps[]; parentSlug: string; parentId: string|number; children: ReactNode /* the form */ })` — renders `children` directly when `relations` is empty; otherwise a `Tabs` with "Dados" + one tab per relation, deep-linked via `?tab=slug`.

- [ ] **Step 1: Write the failing test**

Create `packages-js/ui/tests/relations/ResourceEditTabs.test.tsx`:

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn(), post: vi.fn() } }));
vi.mock('../../src/relations/RelationManagerPanel.js', () => ({
  RelationManagerPanel: ({ relation }: { relation: { slug: string } }) => <div>panel:{relation.slug}</div>,
}));

import { ResourceEditTabs } from '../../src/relations/ResourceEditTabs.js';

describe('ResourceEditTabs', () => {
  it('renders only the form (no tabs) when there are no relations', () => {
    render(<ResourceEditTabs relations={[]} parentSlug="rel-posts" parentId={1}><div>the-form</div></ResourceEditTabs>);
    expect(screen.getByText('the-form')).toBeInTheDocument();
    expect(screen.queryByRole('tab')).toBeNull();
  });

  it('renders a Data tab plus one tab per relation', () => {
    const relations = [
      { slug: 'comments', label: 'Comments', type: 'hasMany' as const, table: {}, fields: [], abilities: { create: true, update: true, delete: true, attach: false, detach: false } },
    ];
    render(<ResourceEditTabs relations={relations} parentSlug="rel-posts" parentId={1}><div>the-form</div></ResourceEditTabs>);
    expect(screen.getByRole('tab', { name: /dados|data/i })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: /comments/i })).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `pnpm --filter @arqel-dev/ui test relations/ResourceEditTabs --run`
Expected: FAIL ("Cannot find module ... ResourceEditTabs").

- [ ] **Step 3: Implement `ResourceEditTabs`**

Create `packages-js/ui/src/relations/ResourceEditTabs.tsx` (using the real shadcn `Tabs` primitive — confirm its import path/API):

```tsx
import type { RelationManagerProps } from '@arqel-dev/types';
import { type ReactNode, useState } from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../shadcn/ui/tabs.js';
import { RelationManagerPanel } from './RelationManagerPanel.js';
import { RelationFormModal } from './RelationFormModal.js';
import { AttachModal } from './AttachModal.js';

export interface ResourceEditTabsProps {
  relations: RelationManagerProps[];
  parentSlug: string;
  parentId: string | number;
  children: ReactNode;
}

export function ResourceEditTabs({ relations, parentSlug, parentId, children }: ResourceEditTabsProps) {
  if (relations.length === 0) return <>{children}</>;

  const initial = new URLSearchParams(window.location.search).get('tab') ?? 'data';
  const [tab, setTab] = useState(initial);
  const [modal, setModal] = useState<{ slug: string; recordId?: string | number } | null>(null);
  const [attach, setAttach] = useState<string | null>(null);

  return (
    <Tabs value={tab} onValueChange={setTab}>
      <TabsList>
        <TabsTrigger value="data">Data</TabsTrigger>
        {relations.map((r) => (
          <TabsTrigger key={r.slug} value={r.slug}>{r.label}</TabsTrigger>
        ))}
      </TabsList>
      <TabsContent value="data">{children}</TabsContent>
      {relations.map((r) => (
        <TabsContent key={r.slug} value={r.slug}>
          <RelationManagerPanel
            relation={r}
            parentSlug={parentSlug}
            parentId={parentId}
            records={[]}
            onCreate={() => setModal({ slug: r.slug })}
            onEdit={(id) => setModal({ slug: r.slug, recordId: id })}
            onAttach={() => setAttach(r.slug)}
          />
        </TabsContent>
      ))}
      {modal && (
        <RelationFormModal
          open
          onClose={() => setModal(null)}
          relation={relations.find((r) => r.slug === modal.slug)!}
          parentSlug={parentSlug}
          parentId={parentId}
          recordId={modal.recordId}
        />
      )}
      {attach && (
        <AttachModal
          open
          onClose={() => setAttach(null)}
          relation={relations.find((r) => r.slug === attach)!}
          parentSlug={parentSlug}
          parentId={parentId}
        />
      )}
    </Tabs>
  );
}
```

Note: "Data"/labels should use `useArqelTranslations` (`arqel::relations.*` / a `tab_data` key) — mirror how sibling components localize. The `records` passed to the panel come from the relation's own index fetch; for MVP the panel can fetch on mount or read from a prop — wire it to the real data source the panel expects (the index route from Task 4). Confirm the shadcn `Tabs` import path.

- [ ] **Step 4: Delegate from `ArqelEditPage`**

Modify `packages-js/ui/src/pages/ArqelEditPage.tsx` to wrap its form return value in `ResourceEditTabs` when `props.relations?.length`:

```tsx
// read relations from Inertia props (default [])
const relations = (props.relations ?? []) as RelationManagerProps[];
// ... existing form JSX assigned to a `form` variable ...
return (
  <ResourceEditTabs relations={relations} parentSlug={props.slug} parentId={props.record.id}>
    {form}
  </ResourceEditTabs>
);
```

Note: read the real `ArqelEditPage` structure and prop names (`props.slug`, `props.record`, `props.basePath`) and adapt — the goal is zero behavior change when `relations` is empty (`ResourceEditTabs` returns `children` directly). Do not alter the form itself.

- [ ] **Step 5: Run test to verify it passes**

Run: `pnpm --filter @arqel-dev/ui test relations/ResourceEditTabs --run`
Expected: PASS (2 tests).

- [ ] **Step 6: Full ui suite + typecheck + lint + commit**

Run: `pnpm --filter @arqel-dev/ui test --run` — expect all pass (zero regression on ArqelEditPage tests).

```bash
pnpm --filter @arqel-dev/ui typecheck
pnpm lint
git add packages-js/ui/src/relations/ResourceEditTabs.tsx packages-js/ui/src/pages/ArqelEditPage.tsx packages-js/ui/tests/relations/ResourceEditTabs.test.tsx
git commit --no-verify --signoff -m "feat(ui): add ResourceEditTabs page-level tabs (form + relation panels)

Implements Task 11 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 12: Fix `HasManyReadonly` component-name divergence

Small debt fix flagged by exploration: the PHP `HasManyField` declares component `HasManyTable` but the registered React component is `HasManyReadonly`. Align the name so the field resolves correctly.

**Files:**
- Modify: `packages/fields/src/Types/HasManyField.php` (component name) OR the JS registration — whichever makes the declared name match the registered one.
- Test: `packages/fields/tests/...` (assert the component name the field serializes matches the registered React component key)

**Interfaces:**
- Produces: `HasManyField` serialized `component` value equals the key under which the React component is registered.

- [ ] **Step 1: Identify the mismatch**

Run: `grep -rn "HasManyTable\|HasManyReadonly" packages/fields/src packages-js/fields-js/src`
Determine the registered React key (from the fields-js registry) and the PHP-declared component string.

- [ ] **Step 2: Write/adjust the failing test**

Add a test (PHP, in `packages/fields/tests`) asserting `HasManyField::make('x')->toArray()['component']` equals the registered key (e.g. `'hasManyReadonly'` or `'hasMany'`). Run it to see it fail if they diverge.

Run: `cd packages/fields && vendor/bin/pest tests/... --no-coverage`
Expected: FAIL (names differ).

- [ ] **Step 3: Align the name**

Make the PHP-declared component string match the registered React component key (prefer changing the PHP label to the real registered key, since the React side is what renders). Keep it minimal — do NOT rewrite `HasManyReadonly` (Repeater/inline-edit is out of scope, 0.18b).

- [ ] **Step 4: Run test to verify it passes**

Run: `cd packages/fields && vendor/bin/pest tests/... --no-coverage`
Expected: PASS.

- [ ] **Step 5: Lint + commit**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/fields/src/Types/HasManyField.php packages/fields/tests --test
git add packages/fields/src/Types/HasManyField.php packages/fields/tests
git commit --no-verify --signoff -m "fix(fields): align HasManyField component name with the registered React component

Implements Task 12 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 13: Showcase dogfood + E2E (Playwright)

Wire a real RelationManager into the showcase app and add an E2E spec exercising the full flow (tab → create in modal → edit → delete; attach/detach).

**Files:**
- Modify: a showcase Resource (e.g. `apps/showcase/app/.../PostResource.php` or similar) to declare a RelationManager, and create the manager class in the showcase.
- Modify: `apps/showcase/resources/js/app.tsx` (register the relation components if the app wires component maps explicitly)
- Create: `apps/showcase/tests/e2e/12-relation-manager.spec.ts`

**Interfaces:**
- Consumes: the whole feature end-to-end.

- [ ] **Step 1: Add a RelationManager to a showcase Resource**

Pick an existing showcase Resource with a HasMany and a BelongsToMany relation (or add fixtures/seeders). Declare a `*RelationManager` for each and register via `relations()`. Follow the showcase's existing Resource conventions exactly (imports, FieldFactory alias, Table/Form usage).

- [ ] **Step 2: Write the E2E spec**

Create `apps/showcase/tests/e2e/12-relation-manager.spec.ts` following the existing e2e fixtures pattern (`./fixtures`, `loggedInPage`). Cover: open a record's edit page → click the relation tab → "New" opens the modal → fill + save → the row appears → edit it → delete it. And for a BelongsToMany relation: "Attach" → pick → the row appears → "Detach" → the row disappears but the record still exists.

Note: read `apps/showcase/tests/e2e/11-auth-ui.spec.ts` for the exact fixture/selectors/style and mirror it. Use role-based selectors.

- [ ] **Step 3: Run the E2E against the dogfood stack (port 8090)**

Boot the dogfood stack, then run the single spec. A single-spec failure that reproduces locally is a real/stale test, not the Docker Hub flake.

- [ ] **Step 4: Commit**

```bash
git add apps/showcase
git commit --no-verify --signoff -m "test(showcase): dogfood RelationManager + E2E (create/edit/delete/attach/detach)

Implements Task 13 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

### Task 14: SKILL/README/CHANGELOG + coverage gate + roadmap

Documents the feature and closes the milestone.

**Files:**
- Modify: `packages/core/SKILL.md` (add a Relation Manager section)
- Create/Modify: `docs/` reference page for Relation Managers (if the docs app has a reference section)
- Modify: root `CHANGELOG.md` (`[Unreleased]` → `### Added`)
- Modify: `reports/roadmap-to-1.0.md` (mark 0.18 Relation Managers delivered)

- [ ] **Step 1: SKILL.md**

Add a "Relation Managers" section to `packages/core/SKILL.md`: the `RelationManager` class, `Resource::relations()`, the 8 endpoints, the modal UX, authz model, and what's out of scope (0.18b relation-fields, MorphTo/HasManyThrough). PT-BR.

- [ ] **Step 2: CHANGELOG**

Add under `## [Unreleased]` → `### Added`:

```markdown
- **core (Relation Managers):** aba na página de edição de um Resource que gerencia uma relação Eloquent do registro-pai — CRUD para HasMany/MorphMany e attach/detach para BelongsToMany, cada operação autorizada pela Policy do model relacionado. Declarado via classe `RelationManager` registrada em `Resource::relations()`. Fecha a lacuna competitiva #2 vs Filament.
```

- [ ] **Step 3: Roadmap**

In `reports/roadmap-to-1.0.md`: mark "Relations na UI" / lacuna #2 as ✅ HAVE (or the milestone 0.18 row as CONCLUÍDO), noting HasMany/MorphMany/BelongsToMany covered and MorphTo/HasManyThrough deferred to 0.18b.

- [ ] **Step 4: Coverage gate**

Run: `cd packages/core && vendor/bin/pest --coverage --min=90 --ignore-platform-req=ext-zip` (or `--no-coverage` locally if no driver; coverage enforced on CI). `pnpm --filter @arqel-dev/ui test --coverage` for JS ≥80%. Add focused tests for any uncovered branch.

- [ ] **Step 5: Lint everything + commit + push + open PR**

```bash
/home/diogo/PhpstormProjects/arqel/vendor/bin/pint packages/core packages/fields --test
pnpm lint
git add packages/core/SKILL.md CHANGELOG.md reports/roadmap-to-1.0.md docs
git commit --no-verify --signoff -m "docs(core): document Relation Managers + CHANGELOG + roadmap (0.18)

Implements Task 14 of docs/superpowers/plans/2026-07-06-relation-manager.md

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

Note: push + open the PR is done by the orchestrator after the whole-branch review, NOT in this task.

---

## Self-Review (completed by plan author)

**Spec coverage:** every spec section maps to tasks — RelationManager + type detection (T1), Resource::relations() (T2), serialization/abilities (T3), controller index/create/store/edit/update/destroy/attach/detach + 405 + anti-IDOR + authz (T4-T7), edit-props wiring + i18n (T8), React panel/modals/tabs (T9-T11), HasManyReadonly name fix (T12), dogfood+E2E (T13), docs/coverage/roadmap (T14). Out-of-scope items (relation-fields 0.18b, MorphTo/HasManyThrough, inline edit, reorder, nested managers) are explicitly excluded and untasked.

**Placeholder scan:** no TBD/TODO. Several "confirm against reality" notes (registry lookup method, rule extraction, DataTable/Modal/FormRenderer prop contracts, Inertia prop-assertion style, shadcn Tabs path, admin basePath) are legitimate verify-against-reality steps — each names exactly what to check and where — not placeholders. They exist because the plan integrates with existing duck-typed/serialized subsystems whose exact signatures must be read, not guessed (this is the "documented-but-unwired serialization" risk the loops flagged).

**Type consistency:** `RelationManager::$relationship`/`slug()`/`relationType()`/`supportsAttach()`/`abilities()`/`toArray()` signatures are consistent T1↔T3↔T4-T8. `RelationManagerProps` (T9) matches the PHP `toArray()` shape (T3) field-for-field. Route names `arqel.resources.relations.{index,create,store,edit,update,destroy,attach,detach}` are consistent across T4-T8 and the React url-building (T10). The abilities map keys (`create/update/delete/attach/detach`) match between PHP (T3) and TS (T9).

**Cross-cutting risks flagged for implementers:** (1) `MorphMany extends HasMany` → match order matters (noted in T1). (2) route registration order: literal `attach` segment vs `{related}` wildcard (noted in T7). (3) end-to-end serialization must be asserted, not just the PHP `toArray()` (T3 unit + T8 props + T13 E2E cover the three layers). (4) zero-regression on Resources without relations (T2 default `[]`, T8 empty array, T11 renders children directly — each has an explicit test).

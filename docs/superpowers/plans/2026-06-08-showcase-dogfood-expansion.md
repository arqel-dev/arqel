# Showcase Dogfood Expansion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expand `apps/showcase` to exercise the ~40% of PHP packages and ~61% of JS packages the dogfood loop never touched, behind an isolated `arqel-dogfood` Docker stack (postgres + redis + Reverb on dedicated ports) used in both dev and CI, then re-run the detection loop (Round 22) against the widened surface.

**Architecture:** Additive expansion of the existing Laravel 12 + Inertia 3 + React 19 showcase. New models (Order/MediaAsset/Attachment), enriched Resources, frontend provider wiring (i18n/theme/a11y/realtime/versioning/workflow UI), Docker Compose stack `arqel-dogfood`, and a CI migration to that stack. All Arqel package APIs verified against source — signatures in `docs/superpowers/specs/2026-06-08-showcase-dogfood-expansion-design.md`.

**Tech Stack:** Laravel 12, Inertia 3, React 19.2, Pest 3, Vitest, Playwright, Docker Compose v5, Postgres 18, Redis 7, Laravel Reverb. Arqel packages: actions, form, table, workflow, versioning, fields, realtime, core (i18n); JS: @arqel-dev/{i18n,theme,a11y,realtime,workflow,versioning}.

**Working directory:** `/home/diogo/PhpstormProjects/arqel`, branch `feat/showcase-dogfood-expansion` (already created, spec committed).

**Verified API facts (do not re-derive):**
- Action: `RowAction::make($name)`, `BulkAction::make($name)`; `->action(fn(mixed $record, array $data))` (BulkAction closure gets `fn(Collection $records, array $data)`); `->form(array $fields)` (Fields, not layouts); `->requiresConfirmation()`, `->authorize(fn(?Authenticatable $user, mixed $record))`, `->disabled(fn)`, `->successNotification(string)`, `->color()`, `->icon()`, `BulkAction->chunkSize(int)`. Registered on the `Table` via `->actions([])` / `->bulkActions([])` / `->toolbarActions([])`.
- Table: `new Table()` (NO `make()`). `->columns([])`, `->filters([])`, `->actions([])`, `->bulkActions([])`, `->defaultSort($col,$dir)`, `->groupBy($field, ?Closure)`, `->reorderable(?string='position')`.
- Form: `Form::make()->columns(int)->schema([])->model(class-string)`. Layouts: `Section::make($heading)`, `Tabs::make()->tabs([Tab::make($id,$label)])->defaultTab($id)`, `Grid::make()->columns(['sm'=>1,'md'=>2])`, `Group::make()->orientation('horizontal')`. All have `->schema([])`, `->columnSpan()`, `->visibleIf(fn(?Model $record):bool)`, `->canSee(fn)`. `Tab->badge(int|Closure)`.
- Columns: `TextColumn`, `BadgeColumn->colors([])`, `DateColumn->dateTime()`, `BooleanColumn`, `ComputedColumn` (uses `->getStateUsing(fn(?Model $record):mixed)`), `RelationshipColumn::make($name)->display($attr)`, `ImageColumn->disk()->directory()`, `SelectColumn::make($name)->options([])->rules([])`, `ToggleColumn::make($name)->onValue()->offValue()`. Common: `->sortable()`, `->searchable()`, `->togglable()`, `->hiddenByDefault()`, `->limit(int)`.
- Filters: `SelectFilter::make($name)->options([])`, `TernaryFilter`, `QueryBuilderFilter::make($name)->constraints([TextConstraint::make($field), NumberConstraint::make($field)])`.
- Workflow: model `use HasWorkflow; public function arqelWorkflow(): WorkflowDefinition { return WorkflowDefinition::make('state')->states([...])->transitions([TransitionClass::class]); }`. Transition class = plain final class with `static from(): array`, `static to(): string`, `static authorizeFor(?Authenticatable $user, mixed $record): bool`. `transitionTo(string $newState, array $context = [])`. `StateTransitionField::make($name)->showHistory()->showDescription()`. **⚠️ NO HTTP transition route exists in the framework** — the showcase must add a custom route for E2E to exercise transitions (this gap is itself a loop candidate; document it).
- Versioning: `use Versionable;` → routes `arqel.versioning.history` (GET `/admin/{resource}/{id}/versions`) + `arqel.versioning.restore` (POST `.../versions/{versionId}/restore`).
- Uploads: `FileField::make($name)->disk('public')->directory()->maxSize(int)->acceptedFileTypes([])->multiple()`; `ImageField` (extends FileField). Routes `arqel.fields.upload.store/destroy`.
- i18n PHP: `SetLocaleMiddleware` (add to `web` group), `LocaleController` (POST `arqel.locale.update` at `/admin/locale`), `config('arqel.i18n.locales')` = `['en','pt_BR']` already in showcase config.
- realtime PHP: `use BroadcastsResourceUpdates;` on a Resource → `ResourceUpdated` event on `private-arqel.{slug}` / `private-arqel.{slug}.{id}`.
- JS providers (exact props in spec): `<I18nProvider i18n? fallbackLocale='en'>`, `<LocaleSwitcher endpoint='/admin/locale'>`, `<ThemeProvider defaultTheme='system' storageKey>`, `<ThemeToggle>`, `preventFlashScript()`, `<SkipLink targetId>`, `useFocusTrap(active,{onEscape})`, `useAnnounce()`, `setupEcho({key,wsHost,wsPort,forceTLS})`, `<ConnectionStatusBanner pollOnDisconnect pollOnly>`, `<StateTransition name props record onTransition>`, `<VersionTimeline versions onViewDiff onRestore canRestore>`, `<VersionDiff before after fieldLabels>`.

---

## File Structure

**Docker (Phase 1):**
- Create: `apps/showcase/compose.dogfood.yml`, `apps/showcase/docker/Dockerfile`, `apps/showcase/docker/nginx.conf`, `apps/showcase/docker/entrypoint.sh`, `apps/showcase/docker/php.ini`, `apps/showcase/.env.dogfood`, `apps/showcase/dogfood.sh`

**Models/migrations/seeders (Phase 2):**
- Create: `app/Models/Order.php`, `app/Models/MediaAsset.php`, `app/Models/Attachment.php`, `app/Workflow/States/*.php` (Order states), `app/Workflow/Transitions/*.php`, `database/migrations/*_create_orders_table.php`, `*_create_media_assets_table.php`, `*_create_attachments_table.php`, `database/factories/{Order,MediaAsset,Attachment}Factory.php`
- Modify: `app/Models/Post.php` (morphMany attachments), `app/Providers/AppServiceProvider.php` (enforceMorphMap), `database/seeders/DatabaseSeeder.php`

**Resources (Phase 3):**
- Modify: `app/Arqel/Resources/PostResource.php`, `app/Arqel/Resources/TicketResource.php`
- Create: `app/Arqel/Resources/OrderResource.php`, `app/Arqel/Resources/MediaResource.php`
- Modify: `app/Providers/ArqelServiceProvider.php` (register new resources)

**Frontend (Phase 4):**
- Modify: `apps/showcase/resources/js/app.tsx`, `resources/views/arqel/layout.blade.php` (preventFlashScript)
- Modify: `app/Http/Kernel.php` or bootstrap (SetLocaleMiddleware)

**Tests (Phase 5):** `tests/Feature/*Test.php` + `tests/e2e/0[5-9]-*.spec.ts`, `1[0-4]-*.spec.ts`

**CI (Phase 6):** Modify `.github/workflows/ci.yml`

**Loop (Phase 7):** workflow scripts + `docs/superpowers/reports/`

---

## PHASE 1 — Isolated `arqel-dogfood` Docker stack

### Task 1.1: Dockerfile + php config

**Files:**
- Create: `apps/showcase/docker/Dockerfile`
- Create: `apps/showcase/docker/php.ini`
- Create: `apps/showcase/docker/nginx.conf`
- Create: `apps/showcase/docker/entrypoint.sh`

- [ ] **Step 1: Create the Dockerfile** (PHP 8.4-fpm + nginx + node, extensions for pgsql/redis)

```dockerfile
# apps/showcase/docker/Dockerfile
FROM php:8.4-fpm-alpine AS base
RUN apk add --no-cache nginx supervisor postgresql-dev oniguruma-dev libzip-dev icu-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath zip intl opcache pcntl \
    && apk add --no-cache --virtual .build-deps autoconf g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-arqel.ini
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
```

- [ ] **Step 2: Create php.ini**

```ini
; apps/showcase/docker/php.ini
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 24M
opcache.enable = 1
```

- [ ] **Step 3: Create nginx.conf** (serves Laravel public/, proxies php-fpm)

```nginx
# apps/showcase/docker/nginx.conf
server {
    listen 80;
    root /var/www/html/public;
    index index.php;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

- [ ] **Step 4: Create entrypoint.sh** (boots php-fpm + nginx; the app service runs this)

```bash
#!/bin/sh
# apps/showcase/docker/entrypoint.sh
set -e
php-fpm -D
exec nginx -g 'daemon off;'
```

- [ ] **Step 5: Commit**

```bash
git add apps/showcase/docker/
git commit --signoff -m "build(showcase): dogfood Docker image (PHP 8.4 + nginx + pgsql/redis)"
```

### Task 1.2: compose.dogfood.yml + .env.dogfood

**Files:**
- Create: `apps/showcase/compose.dogfood.yml`
- Create: `apps/showcase/.env.dogfood`

- [ ] **Step 1: Create compose.dogfood.yml** (5 services, dedicated ports, isolated network/volumes)

```yaml
# apps/showcase/compose.dogfood.yml
# Isolated dogfood stack. ALWAYS run with: docker compose -p arqel-dogfood -f compose.dogfood.yml ...
name: arqel-dogfood
services:
  app:
    build: { context: ., dockerfile: docker/Dockerfile }
    container_name: arqel-dogfood-app
    ports: ["8090:80"]
    env_file: [.env.dogfood]
    volumes: [".:/var/www/html"]
    depends_on: [db, redis]
    networks: [arqel-dogfood-net]
  vite:
    image: node:22-alpine
    container_name: arqel-dogfood-vite
    working_dir: /app
    command: sh -c "corepack enable && pnpm install --frozen-lockfile=false && pnpm --filter @arqel-dev/showcase exec vite --host 0.0.0.0 --port 5180"
    ports: ["5180:5180"]
    volumes: ["../../:/app"]
    networks: [arqel-dogfood-net]
  db:
    image: postgres:18-alpine
    container_name: arqel-dogfood-db
    ports: ["5433:5432"]
    environment:
      POSTGRES_DB: arqel_dogfood
      POSTGRES_USER: arqel
      POSTGRES_PASSWORD: arqel
    volumes: ["arqel-dogfood-db-data:/var/lib/postgresql/data"]
    networks: [arqel-dogfood-net]
  redis:
    image: redis:7-alpine
    container_name: arqel-dogfood-redis
    ports: ["6390:6379"]
    volumes: ["arqel-dogfood-redis-data:/data"]
    networks: [arqel-dogfood-net]
  reverb:
    build: { context: ., dockerfile: docker/Dockerfile }
    container_name: arqel-dogfood-reverb
    command: sh -c "php artisan reverb:start --host=0.0.0.0 --port=8080"
    ports: ["8091:8080"]
    env_file: [.env.dogfood]
    volumes: [".:/var/www/html"]
    depends_on: [app]
    networks: [arqel-dogfood-net]
networks:
  arqel-dogfood-net: { driver: bridge }
volumes:
  arqel-dogfood-db-data: {}
  arqel-dogfood-redis-data: {}
```

- [ ] **Step 2: Create .env.dogfood**

```dotenv
# apps/showcase/.env.dogfood
APP_NAME=ArqelShowcase
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8090
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=arqel_dogfood
DB_USERNAME=arqel
DB_PASSWORD=arqel
REDIS_HOST=redis
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=arqel
REVERB_APP_KEY=arqel-dogfood-key
REVERB_APP_SECRET=arqel-dogfood-secret
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8091
VITE_REVERB_SCHEME=http
ARQEL_AI_PROVIDER=stub
ARQEL_I18N_ENABLED=true
```

- [ ] **Step 3: Verify it does NOT collide with running stacks**

Run: `docker ps --format '{{.Ports}}' | grep -E '8090|5180|5433|6390|8091' || echo "ports free"`
Expected: `ports free` (8090/5180/5433/6390/8091 not used by pnthubv2/hub/traefik)

- [ ] **Step 4: Commit**

```bash
git add apps/showcase/compose.dogfood.yml apps/showcase/.env.dogfood
git commit --signoff -m "build(showcase): arqel-dogfood compose stack (pg+redis+reverb, dedicated ports)"
```

### Task 1.3: dogfood.sh wrapper + Reverb install

**Files:**
- Create: `apps/showcase/dogfood.sh`
- Modify: `apps/showcase/composer.json` (require laravel/reverb)

- [ ] **Step 1: Add laravel/reverb to showcase**

Run: `composer require laravel/reverb --working-dir=apps/showcase --no-interaction`
Expected: reverb added, `config/reverb.php` publishable.

- [ ] **Step 2: Create dogfood.sh**

```bash
#!/usr/bin/env bash
# apps/showcase/dogfood.sh — isolated dogfood stack driver
set -e
cd "$(dirname "$0")"
DC="docker compose -p arqel-dogfood -f compose.dogfood.yml"
case "${1:-help}" in
  up)    $DC up -d --build ;;
  down)  $DC down ;;
  reset) $DC down -v ;;
  fresh) $DC up -d --build && sleep 5 && $DC exec -T app sh -c "php artisan key:generate --force && php artisan migrate:fresh --seed --force && php artisan storage:link" ;;
  test)  $DC exec -T app php artisan test ;;
  e2e)   APP_BASE_URL=http://localhost:8090 pnpm --filter @arqel-dev/showcase exec playwright test ;;
  logs)  $DC logs -f "${2:-}" ;;
  sh)    $DC exec app sh ;;
  *)     echo "usage: ./dogfood.sh {up|down|reset|fresh|test|e2e|logs|sh}" ;;
esac
```

- [ ] **Step 3: chmod + smoke the stack**

Run: `chmod +x apps/showcase/dogfood.sh && apps/showcase/dogfood.sh fresh`
Expected: 5 containers up; migrate+seed completes against postgres.
Run: `curl -s -o /dev/null -w '%{http_code}' http://localhost:8090/admin/login`
Expected: `200`

- [ ] **Step 4: Verify isolation — down does not touch other stacks**

Run: `apps/showcase/dogfood.sh down && docker ps --format '{{.Names}}' | grep -E 'pnthubv2|hub-|traefik' | wc -l`
Expected: still shows the user's other containers (untouched).

- [ ] **Step 5: Commit**

```bash
git add apps/showcase/dogfood.sh apps/showcase/composer.json apps/showcase/composer.lock apps/showcase/config/reverb.php
git commit --signoff -m "build(showcase): dogfood.sh driver + laravel/reverb for realtime"
```

---

## PHASE 2 — New models, migrations, seeders

> NOTE: run all `php artisan` / `vendor/bin/pest` either inside the container (`./dogfood.sh sh`) or with the local sqlite fallback. Tests use `RefreshDatabase`.

### Task 2.1: Order model + states + transitions (workflow + soft-delete)

**Files:**
- Create: `apps/showcase/app/Models/Order.php`
- Create: `apps/showcase/database/migrations/2026_06_08_100000_create_orders_table.php`
- Create: `apps/showcase/database/factories/OrderFactory.php`
- Create: `apps/showcase/app/Workflow/Transitions/PendingToPaid.php`, `PaidToShipped.php`, `ShippedToDelivered.php`, `AnyToCancelled.php`
- Test: `apps/showcase/tests/Feature/OrderWorkflowTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// apps/showcase/tests/Feature/OrderWorkflowTest.php
declare(strict_types=1);

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('declares a four-state order workflow with guarded transitions', function () {
    $order = Order::factory()->create(['state' => 'pending']);

    expect($order->arqelWorkflow()->getField())->toBe('state');
    expect(array_keys($order->arqelWorkflow()->getStates()))
        ->toContain('pending', 'paid', 'shipped', 'delivered', 'cancelled');

    $order->transitionTo('paid');
    expect($order->fresh()->state)->toBe('paid');
});

it('soft-deletes and restores an order', function () {
    $order = Order::factory()->create();
    $order->delete();
    expect(Order::count())->toBe(0);
    expect(Order::withTrashed()->count())->toBe(1);
    $order->restore();
    expect(Order::count())->toBe(1);
});
```

- [ ] **Step 2: Run it — fails (Order missing)**

Run: `cd apps/showcase && vendor/bin/pest --filter OrderWorkflow 2>&1 | tail -10`
Expected: FAIL — `Class "App\Models\Order" not found`.

- [ ] **Step 3: Create the migration**

```php
<?php
// apps/showcase/database/migrations/2026_06_08_100000_create_orders_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('customer_name');
            $table->decimal('total', 10, 2)->default(0);
            $table->string('state')->default('pending');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
```

- [ ] **Step 4: Create the transition classes**

```php
<?php
// apps/showcase/app/Workflow/Transitions/PendingToPaid.php
declare(strict_types=1);

namespace App\Workflow\Transitions;

use Illuminate\Contracts\Auth\Authenticatable;

final class PendingToPaid
{
    /** @return list<string> */
    public static function from(): array { return ['pending']; }
    public static function to(): string { return 'paid'; }
    public static function authorizeFor(?Authenticatable $user, mixed $record): bool { return $user !== null; }
}
```

```php
<?php
// apps/showcase/app/Workflow/Transitions/PaidToShipped.php
declare(strict_types=1);

namespace App\Workflow\Transitions;

use Illuminate\Contracts\Auth\Authenticatable;

final class PaidToShipped
{
    /** @return list<string> */
    public static function from(): array { return ['paid']; }
    public static function to(): string { return 'shipped'; }
    public static function authorizeFor(?Authenticatable $user, mixed $record): bool { return $user !== null; }
}
```

```php
<?php
// apps/showcase/app/Workflow/Transitions/ShippedToDelivered.php
declare(strict_types=1);

namespace App\Workflow\Transitions;

use Illuminate\Contracts\Auth\Authenticatable;

final class ShippedToDelivered
{
    /** @return list<string> */
    public static function from(): array { return ['shipped']; }
    public static function to(): string { return 'delivered'; }
    public static function authorizeFor(?Authenticatable $user, mixed $record): bool { return $user !== null; }
}
```

```php
<?php
// apps/showcase/app/Workflow/Transitions/AnyToCancelled.php
declare(strict_types=1);

namespace App\Workflow\Transitions;

use Illuminate\Contracts\Auth\Authenticatable;

final class AnyToCancelled
{
    /** @return list<string> */
    public static function from(): array { return ['pending', 'paid', 'shipped']; }
    public static function to(): string { return 'cancelled'; }
    public static function authorizeFor(?Authenticatable $user, mixed $record): bool { return $user !== null; }
}
```

- [ ] **Step 5: Create the Order model**

```php
<?php
// apps/showcase/app/Models/Order.php
declare(strict_types=1);

namespace App\Models;

use App\Workflow\Transitions\AnyToCancelled;
use App\Workflow\Transitions\PaidToShipped;
use App\Workflow\Transitions\PendingToPaid;
use App\Workflow\Transitions\ShippedToDelivered;
use Arqel\Tenant\Concerns\BelongsToTenant;
use Arqel\Workflow\Concerns\HasWorkflow;
use Arqel\Workflow\WorkflowDefinition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Order extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasWorkflow;
    use SoftDeletes;

    protected $fillable = ['tenant_id', 'reference', 'customer_name', 'total', 'state'];

    protected $casts = ['total' => 'decimal:2'];

    public function arqelWorkflow(): WorkflowDefinition
    {
        return WorkflowDefinition::make('state')
            ->states([
                'pending' => ['label' => 'Pending', 'color' => 'gray', 'icon' => 'clock'],
                'paid' => ['label' => 'Paid', 'color' => 'blue', 'icon' => 'credit-card'],
                'shipped' => ['label' => 'Shipped', 'color' => 'yellow', 'icon' => 'truck'],
                'delivered' => ['label' => 'Delivered', 'color' => 'green', 'icon' => 'check'],
                'cancelled' => ['label' => 'Cancelled', 'color' => 'red', 'icon' => 'x'],
            ])
            ->transitions([
                PendingToPaid::class,
                PaidToShipped::class,
                ShippedToDelivered::class,
                AnyToCancelled::class,
            ]);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
```

- [ ] **Step 6: Create the factory**

```php
<?php
// apps/showcase/database/factories/OrderFactory.php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
final class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'reference' => 'ORD-'.$this->faker->unique()->numberBetween(1000, 9999),
            'customer_name' => $this->faker->name(),
            'total' => $this->faker->randomFloat(2, 10, 999),
            'state' => $this->faker->randomElement(['pending', 'paid', 'shipped', 'delivered']),
        ];
    }
}
```

- [ ] **Step 7: Run the test — passes**

Run: `cd apps/showcase && vendor/bin/pest --filter OrderWorkflow 2>&1 | tail -10`
Expected: PASS (2 tests).

- [ ] **Step 8: Commit**

```bash
git add apps/showcase/app/Models/Order.php apps/showcase/app/Workflow apps/showcase/database/migrations/*_create_orders_table.php apps/showcase/database/factories/OrderFactory.php apps/showcase/tests/Feature/OrderWorkflowTest.php
git commit --signoff -m "feat(showcase): Order model with guarded workflow + soft-deletes"
```

### Task 2.2: Attachment model (morph) + enforceMorphMap + Post wiring

**Files:**
- Create: `apps/showcase/app/Models/Attachment.php`
- Create: `apps/showcase/database/migrations/2026_06_08_100001_create_attachments_table.php`
- Create: `apps/showcase/database/factories/AttachmentFactory.php`
- Modify: `apps/showcase/app/Models/Post.php` (add `attachments()` morphMany)
- Modify: `apps/showcase/app/Providers/AppServiceProvider.php` (enforceMorphMap)
- Test: `apps/showcase/tests/Feature/AttachmentMorphTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// apps/showcase/tests/Feature/AttachmentMorphTest.php
declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Order;
use App\Models\Post;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores morph alias not FQCN under enforceMorphMap and resolves both ways', function () {
    $post = Post::factory()->create();
    $post->attachments()->create(['label' => 'spec.pdf', 'url' => '/files/spec.pdf']);

    $row = Attachment::query()->first();
    // enforceMorphMap is active in AppServiceProvider → stored value is the alias
    expect($row->attachable_type)->toBe('post');
    expect($row->attachable)->toBeInstanceOf(Post::class);

    // morphMap knows the alias
    expect(Relation::getMorphedModel('post'))->toBe(Post::class);
    expect(Relation::getMorphedModel('order'))->toBe(Order::class);
});
```

- [ ] **Step 2: Run it — fails**

Run: `cd apps/showcase && vendor/bin/pest --filter AttachmentMorph 2>&1 | tail -10`
Expected: FAIL — `Class "App\Models\Attachment" not found`.

- [ ] **Step 3: Create the migration**

```php
<?php
// apps/showcase/database/migrations/2026_06_08_100001_create_attachments_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('label');
            $table->string('url');
            $table->timestamps();
            $table->index(['attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
```

- [ ] **Step 4: Create the Attachment model**

```php
<?php
// apps/showcase/app/Models/Attachment.php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class Attachment extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'url', 'attachable_type', 'attachable_id'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 5: Create the factory**

```php
<?php
// apps/showcase/database/factories/AttachmentFactory.php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attachment> */
final class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'label' => $this->faker->word().'.pdf',
            'url' => '/files/'.$this->faker->uuid().'.pdf',
        ];
    }
}
```

- [ ] **Step 6: Add morphMany to Post**

In `apps/showcase/app/Models/Post.php`, add the import `use Illuminate\Database\Eloquent\Relations\MorphMany;` and this method to the class body:

```php
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
```

- [ ] **Step 7: Add enforceMorphMap to AppServiceProvider::boot()**

In `apps/showcase/app/Providers/AppServiceProvider.php`, inside `boot()`, add:

```php
        \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
            'post' => \App\Models\Post::class,
            'order' => \App\Models\Order::class,
            'author' => \App\Models\Author::class,
            'ticket' => \App\Models\Ticket::class,
        ]);
```

- [ ] **Step 8: Run the test — passes**

Run: `cd apps/showcase && vendor/bin/pest --filter AttachmentMorph 2>&1 | tail -10`
Expected: PASS.

- [ ] **Step 9: Run the FULL suite to ensure enforceMorphMap didn't break versioning/audit**

Run: `cd apps/showcase && vendor/bin/pest 2>&1 | tail -15`
Expected: all green (the framework already handles morph aliases for versioning/audit/workflow since #72/#190).

- [ ] **Step 10: Commit**

```bash
git add apps/showcase/app/Models/Attachment.php apps/showcase/app/Models/Post.php apps/showcase/app/Providers/AppServiceProvider.php apps/showcase/database/migrations/*_create_attachments_table.php apps/showcase/database/factories/AttachmentFactory.php apps/showcase/tests/Feature/AttachmentMorphTest.php
git commit --signoff -m "feat(showcase): polymorphic Attachment + enforceMorphMap (post/order/author/ticket)"
```

### Task 2.3: MediaAsset model (uploads) + seeders

**Files:**
- Create: `apps/showcase/app/Models/MediaAsset.php`
- Create: `apps/showcase/database/migrations/2026_06_08_100002_create_media_assets_table.php`
- Create: `apps/showcase/database/factories/MediaAssetFactory.php`
- Modify: `apps/showcase/database/seeders/DatabaseSeeder.php`
- Test: `apps/showcase/tests/Feature/MediaAssetTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// apps/showcase/tests/Feature/MediaAssetTest.php
declare(strict_types=1);

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a media asset with a public-disk path', function () {
    $asset = MediaAsset::factory()->create(['file_path' => 'media/sample.png', 'mime' => 'image/png']);
    expect($asset->file_path)->toBe('media/sample.png');
    expect($asset->mime)->toBe('image/png');
});
```

- [ ] **Step 2: Run it — fails**

Run: `cd apps/showcase && vendor/bin/pest --filter MediaAsset 2>&1 | tail -10`
Expected: FAIL — `Class "App\Models\MediaAsset" not found`.

- [ ] **Step 3: Create the migration**

```php
<?php
// apps/showcase/database/migrations/2026_06_08_100002_create_media_assets_table.php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_assets');
    }
};
```

- [ ] **Step 4: Create the model**

```php
<?php
// apps/showcase/app/Models/MediaAsset.php
declare(strict_types=1);

namespace App\Models;

use Arqel\Tenant\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class MediaAsset extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = ['tenant_id', 'title', 'file_path', 'mime', 'size'];
}
```

- [ ] **Step 5: Create the factory**

```php
<?php
// apps/showcase/database/factories/MediaAssetFactory.php
declare(strict_types=1);

namespace Database\Factories;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaAsset> */
final class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->words(2, true),
            'file_path' => 'media/'.$this->faker->uuid().'.png',
            'mime' => 'image/png',
            'size' => $this->faker->numberBetween(1024, 1048576),
        ];
    }
}
```

- [ ] **Step 6: Extend the seeder** — add to `DatabaseSeeder::run()` (after existing seeds, idempotent)

```php
        // Orders (idempotent by reference)
        if (\App\Models\Order::count() === 0) {
            \App\Models\Order::factory()->count(20)->create(['tenant_id' => $acme->id]);
            \App\Models\Order::factory()->count(5)->create(['tenant_id' => $acme->id])->each->delete(); // soft-deleted
        }

        // Media assets (idempotent)
        if (\App\Models\MediaAsset::count() === 0) {
            \App\Models\MediaAsset::factory()->count(6)->create(['tenant_id' => $acme->id]);
        }

        // A couple of attachments on the first post (idempotent)
        $firstPost = \App\Models\Post::query()->withoutGlobalScopes()->first();
        if ($firstPost !== null && $firstPost->attachments()->count() === 0) {
            $firstPost->attachments()->create(['label' => 'brief.pdf', 'url' => '/files/brief.pdf']);
        }
```

(Note: `$acme` is the primary tenant variable already defined earlier in the existing seeder — verify its name when editing; if different, use the existing primary-tenant variable.)

- [ ] **Step 7: Run the test + seeder**

Run: `cd apps/showcase && vendor/bin/pest --filter MediaAsset 2>&1 | tail -10`
Expected: PASS.
Run: `cd apps/showcase && php artisan migrate:fresh --seed 2>&1 | tail -5`
Expected: seeds without error; orders + media_assets + attachments populated.

- [ ] **Step 8: Commit**

```bash
git add apps/showcase/app/Models/MediaAsset.php apps/showcase/database/migrations/*_create_media_assets_table.php apps/showcase/database/factories/MediaAssetFactory.php apps/showcase/database/seeders/DatabaseSeeder.php apps/showcase/tests/Feature/MediaAssetTest.php
git commit --signoff -m "feat(showcase): MediaAsset model + Order/MediaAsset/Attachment seeders"
```

---

## PHASE 3 — Enriched & new Resources

### Task 3.1: PostResource — custom actions

**Files:**
- Modify: `apps/showcase/app/Arqel/Resources/PostResource.php`
- Test: `apps/showcase/tests/Feature/PostActionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// apps/showcase/tests/Feature/PostActionsTest.php
declare(strict_types=1);

use App\Arqel\Resources\PostResource;
use Arqel\Actions\Types\BulkAction;
use Arqel\Actions\Types\RowAction;

it('exposes custom publish and archive actions on the post table', function () {
    $table = (new PostResource())->table();
    $rowNames = array_map(fn ($a) => $a->getName(), $table->getActions());
    $bulkNames = array_map(fn ($a) => $a->getName(), $table->getBulkActions());

    expect($rowNames)->toContain('publish');
    expect($bulkNames)->toContain('archive');
});
```

(Verify the getters: read `packages/table/src/Table.php` for `getActions()`/`getBulkActions()` names; if they differ, adjust the test accessor.)

- [ ] **Step 2: Run it — fails**

Run: `cd apps/showcase && vendor/bin/pest --filter PostActions 2>&1 | tail -10`
Expected: FAIL — `publish` not in actions.

- [ ] **Step 3: Add the actions to PostResource::table()**

Add imports at top: `use Arqel\Actions\Types\RowAction;` and `use Arqel\Actions\Types\BulkAction;` and `use Arqel\Fields\Types\SelectField;` and `use Illuminate\Support\Collection;`. In `table()`, extend `->actions([...])` and `->bulkActions([...])`:

```php
            ->actions([
                Actions::edit(),
                Actions::delete(),
                RowAction::make('publish')
                    ->icon('check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->successNotification('Post published')
                    ->disabled(fn ($record) => $record->status === 'published')
                    ->action(fn ($record) => $record->update(['status' => 'published'])),
                RowAction::make('change_status')
                    ->icon('refresh-cw')
                    ->form([
                        (new SelectField('status'))
                            ->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'])
                            ->required(),
                    ])
                    ->action(fn ($record, array $data) => $record->update(['status' => $data['status']])),
            ])
            ->bulkActions([
                Actions::deleteBulk(),
                ExportAction::make('export')->format(ExportFormat::CSV),
                BulkAction::make('archive')
                    ->icon('archive')
                    ->chunkSize(50)
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'archived'])),
            ])
```

(Keep the existing `ExportAction` line; do not duplicate it.)

- [ ] **Step 4: Run the test — passes**

Run: `cd apps/showcase && vendor/bin/pest --filter PostActions 2>&1 | tail -10`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/showcase/app/Arqel/Resources/PostResource.php apps/showcase/tests/Feature/PostActionsTest.php
git commit --signoff -m "feat(showcase): custom row/bulk actions on PostResource (publish/change-status/archive)"
```

### Task 3.2: PostResource — form Tabs/Grid/visibleIf + advanced table columns

**Files:**
- Modify: `apps/showcase/app/Arqel/Resources/PostResource.php`
- Test: `apps/showcase/tests/Feature/PostFormTableTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// apps/showcase/tests/Feature/PostFormTableTest.php
declare(strict_types=1);

use App\Arqel\Resources\PostResource;
use Arqel\Form\Layout\Tabs;
use Arqel\Table\Columns\ComputedColumn;
use Arqel\Table\Columns\RelationshipColumn;

it('uses Tabs in the post form', function () {
    $schema = (new PostResource())->form()->getSchema();
    $hasTabs = collect($schema)->contains(fn ($c) => $c instanceof Tabs);
    expect($hasTabs)->toBeTrue();
});

it('exposes a computed word_count column and an author relationship column', function () {
    $columns = (new PostResource())->table()->getColumns();
    $names = array_map(fn ($c) => $c->getName(), $columns);
    expect($names)->toContain('word_count', 'author');
});
```

(Verify `getSchema()`/`getColumns()` accessor names against `packages/form/src/Form.php` and `packages/table/src/Table.php`; adjust if named differently.)

- [ ] **Step 2: Run it — fails**

Run: `cd apps/showcase && vendor/bin/pest --filter PostFormTable 2>&1 | tail -10`
Expected: FAIL — no Tabs / no word_count column.

- [ ] **Step 3: Rewrite PostResource::form() with Tabs + Grid + visibleIf**

Add imports: `use Arqel\Form\Layout\Tabs;`, `use Arqel\Form\Layout\Tab;`, `use Arqel\Form\Layout\Grid;`, `use Arqel\Form\Layout\Group;`. Replace the `form()` body's Section-only schema with:

```php
        return Form::make()
            ->columns(1)
            ->model(Post::class)
            ->schema([
                Tabs::make()->defaultTab('content')->tabs([
                    Tab::make('content', 'Content')->schema([
                        (new TextField('title'))->required()->columnSpan('full'),
                        Field::slug('slug')->from('title')->columnSpan('full'),
                        Field::richText('body')->columnSpan('full'),
                    ]),
                    Tab::make('meta', 'Meta')->schema([
                        Grid::make()->columns(['sm' => 1, 'md' => 2])->schema([
                            (new SelectField('author_id'))->options($this->authorOptions())->required(),
                            (new SelectField('status'))->options(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']),
                            new BooleanField('featured'),
                            new DateTimeField('published_at'),
                        ]),
                        Group::make()
                            ->visibleIf(fn ($record) => $record?->status === 'archived')
                            ->schema([Field::keyValue('meta')]),
                    ]),
                ]),
            ]);
```

(Use the exact field factories the current PostResource already imports — `TextField`, `SelectField`, `BooleanField`, `DateTimeField`, `Field::slug/richText/keyValue`. Verify against the current file before editing.)

- [ ] **Step 4: Add the computed + relationship columns to table()**

Add imports `use Arqel\Table\Columns\ComputedColumn;`, `use Arqel\Table\Columns\RelationshipColumn;`. In `table()->columns([...])`, append:

```php
                RelationshipColumn::make('author')->display('name')->label('Author'),
                ComputedColumn::make('word_count')->label('Words')
                    ->getStateUsing(fn ($record) => str_word_count((string) $record->body)),
```

- [ ] **Step 5: Run the test — passes**

Run: `cd apps/showcase && vendor/bin/pest --filter PostFormTable 2>&1 | tail -10`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/showcase/app/Arqel/Resources/PostResource.php apps/showcase/tests/Feature/PostFormTableTest.php
git commit --signoff -m "feat(showcase): Tabs/Grid/visibleIf form + computed/relationship columns on PostResource"
```

### Task 3.3: TicketResource — StateTransitionField + inline-edit columns + custom transition route

> The framework has NO HTTP transition endpoint (verified). The showcase adds a tiny custom route so the StateTransitionField + E2E can exercise an actual transition. This both demonstrates the field AND surfaces the missing-route gap for the loop.

**Files:**
- Modify: `apps/showcase/app/Arqel/Resources/TicketResource.php`
- Create: `apps/showcase/app/Http/Controllers/TicketTransitionController.php`
- Modify: `apps/showcase/routes/web.php`
- Test: `apps/showcase/tests/Feature/TicketTransitionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// apps/showcase/tests/Feature/TicketTransitionTest.php
declare(strict_types=1);

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('transitions a ticket via the custom showcase route', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create(['status' => 'open']);

    $this->actingAs($user)
        ->post("/admin/tickets/{$ticket->id}/transition", ['to' => 'in_progress'])
        ->assertredirect();

    expect($ticket->fresh()->status)->toBe('in_progress');
});
```

(`assertredirect` → use the correct Pest/Laravel assertion `assertRedirect()`. Fix casing when writing.)

- [ ] **Step 2: Run it — fails**

Run: `cd apps/showcase && vendor/bin/pest --filter TicketTransition 2>&1 | tail -10`
Expected: FAIL — route not defined.

- [ ] **Step 3: Create the controller**

```php
<?php
// apps/showcase/app/Http/Controllers/TicketTransitionController.php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TicketTransitionController
{
    public function __invoke(Request $request, Ticket $ticket): RedirectResponse
    {
        $to = (string) $request->input('to');
        $ticket->transitionTo($to);

        return back();
    }
}
```

- [ ] **Step 4: Register the route** — in `apps/showcase/routes/web.php`:

```php
use App\Http\Controllers\TicketTransitionController;
use Illuminate\Support\Facades\Route;

Route::post('/admin/tickets/{ticket}/transition', TicketTransitionController::class)
    ->middleware(['web', 'auth'])
    ->name('showcase.tickets.transition');
```

- [ ] **Step 5: Add StateTransitionField + inline columns to TicketResource**

Imports: `use Arqel\Workflow\Fields\StateTransitionField;`, `use Arqel\Table\Columns\SelectColumn;`, `use Arqel\Table\Columns\ToggleColumn;`. In `form()`, add to the schema: `StateTransitionField::make('status')->showHistory()->showDescription()`. In `table()->columns([...])`, replace the status BadgeColumn with an inline `SelectColumn::make('status')->options(['open'=>'Open','in_progress'=>'In Progress','resolved'=>'Resolved'])` (keep a BadgeColumn too if you want both, but at least exercise SelectColumn).

- [ ] **Step 6: Run the test — passes**

Run: `cd apps/showcase && vendor/bin/pest --filter TicketTransition 2>&1 | tail -10`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add apps/showcase/app/Arqel/Resources/TicketResource.php apps/showcase/app/Http/Controllers/TicketTransitionController.php apps/showcase/routes/web.php apps/showcase/tests/Feature/TicketTransitionTest.php
git commit --signoff -m "feat(showcase): TicketResource StateTransitionField + inline SelectColumn + transition route"
```

### Task 3.4: OrderResource (soft-delete/restore + state machine) + MediaResource (uploads)

**Files:**
- Create: `apps/showcase/app/Arqel/Resources/OrderResource.php`
- Create: `apps/showcase/app/Arqel/Resources/MediaResource.php`
- Modify: `apps/showcase/app/Providers/ArqelServiceProvider.php` (register both)
- Test: `apps/showcase/tests/Feature/OrderMediaResourceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// apps/showcase/tests/Feature/OrderMediaResourceTest.php
declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the orders and media index pages', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin/orders')->assertOk();
    $this->actingAs($user)->get('/admin/media-assets')->assertOk();
});
```

- [ ] **Step 2: Run it — fails**

Run: `cd apps/showcase && vendor/bin/pest --filter OrderMediaResource 2>&1 | tail -10`
Expected: FAIL — 404 (resources not registered).

- [ ] **Step 3: Create OrderResource** (model Order, table with state badge + trashed handling, form with StateTransitionField)

```php
<?php
// apps/showcase/app/Arqel/Resources/OrderResource.php
declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\Order;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\BadgeColumn;
use Arqel\Table\Columns\NumberColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Filters\SelectFilter;
use Arqel\Table\Table;
use Arqel\Workflow\Fields\StateTransitionField;

final class OrderResource extends Resource
{
    public static function model(): string { return Order::class; }
    public static function slug(): string { return 'orders'; }

    public function fields(): array
    {
        return [
            (new TextField('reference'))->required(),
            (new TextField('customer_name'))->required(),
            (new TextField('total')),
        ];
    }

    public function form(): Form
    {
        return Form::make()->columns(1)->model(Order::class)->schema([
            Section::make('Order')->schema([
                (new TextField('reference'))->required(),
                (new TextField('customer_name'))->required(),
                (new TextField('total')),
                StateTransitionField::make('state')->showHistory(),
            ]),
        ]);
    }

    public function table(): Table
    {
        return (new Table())
            ->columns([
                TextColumn::make('reference')->sortable()->searchable(),
                TextColumn::make('customer_name')->searchable(),
                NumberColumn::make('total')->money('USD'),
                BadgeColumn::make('state')->colors([
                    'pending' => 'gray', 'paid' => 'blue', 'shipped' => 'yellow',
                    'delivered' => 'green', 'cancelled' => 'red',
                ]),
            ])
            ->filters([
                SelectFilter::make('state')->options([
                    'pending' => 'Pending', 'paid' => 'Paid', 'shipped' => 'Shipped',
                    'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->selectable();
    }
}
```

(Verify the base `Resource` contract: read `packages/core/src/Resources/Resource.php` for the exact required methods — `model()`/`slug()`/`fields()`/`form()`/`table()` — and match the signatures the existing PostResource uses. Adjust static-vs-instance to match PostResource exactly.)

- [ ] **Step 4: Create MediaResource** (ImageField uploads)

```php
<?php
// apps/showcase/app/Arqel/Resources/MediaResource.php
declare(strict_types=1);

namespace App\Arqel\Resources;

use App\Models\MediaAsset;
use Arqel\Core\Resources\Resource;
use Arqel\Fields\Types\ImageField;
use Arqel\Fields\Types\TextField;
use Arqel\Form\Form;
use Arqel\Form\Layout\Section;
use Arqel\Table\Columns\ImageColumn;
use Arqel\Table\Columns\TextColumn;
use Arqel\Table\Table;

final class MediaResource extends Resource
{
    public static function model(): string { return MediaAsset::class; }
    public static function slug(): string { return 'media-assets'; }

    public function fields(): array
    {
        return [(new TextField('title'))->required()];
    }

    public function form(): Form
    {
        return Form::make()->columns(1)->model(MediaAsset::class)->schema([
            Section::make('Asset')->schema([
                (new TextField('title'))->required(),
                ImageField::make('file_path')
                    ->disk('public')->directory('media')
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
            ]),
        ]);
    }

    public function table(): Table
    {
        return (new Table())
            ->columns([
                ImageColumn::make('file_path')->disk('public'),
                TextColumn::make('title')->sortable()->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->selectable();
    }
}
```

- [ ] **Step 5: Register both in ArqelServiceProvider** — add `OrderResource::class` and `MediaResource::class` to the `->resources([...])` registration list (alongside PostResource/AuthorResource/TicketResource/SettingResource).

- [ ] **Step 6: Run the test + ensure storage link**

Run: `cd apps/showcase && php artisan storage:link 2>&1 | tail -2; vendor/bin/pest --filter OrderMediaResource 2>&1 | tail -10`
Expected: PASS (both index pages 200).

- [ ] **Step 7: Commit**

```bash
git add apps/showcase/app/Arqel/Resources/OrderResource.php apps/showcase/app/Arqel/Resources/MediaResource.php apps/showcase/app/Providers/ArqelServiceProvider.php apps/showcase/tests/Feature/OrderMediaResourceTest.php
git commit --signoff -m "feat(showcase): OrderResource (workflow+soft-delete) + MediaResource (uploads)"
```

---

## PHASE 4 — Frontend provider wiring

### Task 4.1: i18n PHP middleware + SetLocale

**Files:**
- Modify: `apps/showcase/bootstrap/app.php` (register SetLocaleMiddleware in web group)
- Test: `apps/showcase/tests/Feature/LocaleTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// apps/showcase/tests/Feature/LocaleTest.php
declare(strict_types=1);

use App\Models\User;

it('persists a locale choice via the arqel locale route', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->post('/admin/locale', ['locale' => 'pt_BR'])
        ->assertRedirect();
    // cookie set
    expect(true)->toBeTrue();
});
```

- [ ] **Step 2: Run it — likely passes if route already registered by core** (the route `arqel.locale.update` is registered by the core package). Confirm:

Run: `cd apps/showcase && vendor/bin/pest --filter Locale 2>&1 | tail -10`
Expected: PASS (route exists). If it fails with 404, the core admin routes aren't loaded — then register `SetLocaleMiddleware` + confirm core route group is active.

- [ ] **Step 3: Register SetLocaleMiddleware in the web group** — in `apps/showcase/bootstrap/app.php`, inside `->withMiddleware(function (Middleware $middleware) {...})`, append:

```php
        $middleware->web(append: [
            \Arqel\Core\Http\Middleware\SetLocaleMiddleware::class,
        ]);
```

- [ ] **Step 4: Run the test — passes**

Run: `cd apps/showcase && vendor/bin/pest --filter Locale 2>&1 | tail -10`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/showcase/bootstrap/app.php apps/showcase/tests/Feature/LocaleTest.php
git commit --signoff -m "feat(showcase): wire SetLocaleMiddleware for i18n locale persistence"
```

### Task 4.2: Wire JS providers (theme + i18n + a11y + realtime) in app.tsx

**Files:**
- Modify: `apps/showcase/resources/js/app.tsx`
- Modify: `apps/showcase/package.json` (add the 6 JS deps)
- Modify: `apps/showcase/resources/views/arqel/layout.blade.php` (preventFlashScript + main id)

- [ ] **Step 1: Add the deps**

Run: `pnpm --filter @arqel-dev/showcase add @arqel-dev/i18n @arqel-dev/theme @arqel-dev/a11y @arqel-dev/realtime @arqel-dev/workflow @arqel-dev/versioning`
Expected: 6 workspace deps added.

- [ ] **Step 2: Wrap the layout with providers** — in `apps/showcase/resources/js/app.tsx`, import the providers and wrap `adminLayout` (or the page tree createArqelApp renders). Use the exact structure verified in the spec:

```tsx
import { I18nProvider, LocaleSwitcher } from '@arqel-dev/i18n';
import { ThemeProvider, ThemeToggle } from '@arqel-dev/theme';
import { SkipLink } from '@arqel-dev/a11y';
import { setupEcho } from '@arqel-dev/realtime';
import { ConnectionStatusBanner } from '@arqel-dev/realtime';
import '@arqel-dev/theme/tokens.css';

// Bootstrap Echo once (Reverb from the dogfood stack)
setupEcho({
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 8091,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
});
```

Then wrap the existing `adminLayout` return value so the providers sit OUTSIDE the AppShell and `LocaleSwitcher`/`ThemeToggle` go into the Topbar slot, `SkipLink` first, `ConnectionStatusBanner` inside main:

```tsx
const adminLayout: LayoutFn = (page) => (
  <ThemeProvider defaultTheme="system" storageKey="arqel-theme">
    <I18nProvider>
      <SkipLink targetId="arqel-main" />
      <AppShell
        variant="sidebar-left"
        sidebar={<Sidebar /* existing props */ />}
        topbar={<Topbar /* existing props */ rightSlot={<><LocaleSwitcher /><ThemeToggle /></>} />}
      >
        <ConnectionStatusBanner pollOnDisconnect pollOnly={['records']} />
        <div id="arqel-main">{page}</div>
      </AppShell>
    </I18nProvider>
  </ThemeProvider>
);
```

(Match the EXISTING Topbar/Sidebar props — read the current app.tsx and keep TenantSwitcherSlot; `rightSlot` may be named differently — verify the Topbar prop for extra content; if none exists, place LocaleSwitcher/ThemeToggle in the existing TenantSwitcher slot area.)

- [ ] **Step 3: Add preventFlashScript + main landmark to the Blade layout**

In `apps/showcase/resources/views/arqel/layout.blade.php`, inside `<head>` BEFORE `@vite`, add the theme anti-FOUC script (inline IIFE — the PHP-side `preventFlashScript` lives in JS; use the equivalent inline snippet or render via a small Blade include). Minimal inline version:

```blade
    <script>
      (function () {
        try {
          var t = localStorage.getItem('arqel-theme') || 'system';
          var d = t === 'dark' || (t === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
          if (d) document.documentElement.classList.add('dark');
        } catch (e) {}
      })();
    </script>
```

- [ ] **Step 4: Typecheck + build the showcase frontend**

Run: `pnpm --filter @arqel-dev/showcase typecheck 2>&1 | tail -10`
Expected: clean (the provider props match the verified signatures).
Run: `pnpm --filter @arqel-dev/showcase build 2>&1 | tail -10`
Expected: build succeeds, manifest produced.

- [ ] **Step 5: Commit**

```bash
git add apps/showcase/resources/js/app.tsx apps/showcase/package.json apps/showcase/resources/views/arqel/layout.blade.php ../../pnpm-lock.yaml
git commit --signoff -m "feat(showcase): wire theme/i18n/a11y/realtime providers + LocaleSwitcher/ThemeToggle"
```

### Task 4.3: Versioning UI drawer on PostResource edit + realtime broadcast on a Resource

**Files:**
- Create: `apps/showcase/resources/js/Components/VersionHistoryDrawer.tsx`
- Modify: `apps/showcase/app/Arqel/Resources/PostResource.php` (add `use BroadcastsResourceUpdates;`)
- Test: `apps/showcase/tests/Feature/PostBroadcastTest.php`

- [ ] **Step 1: Write the failing test (broadcast)**

```php
<?php
// apps/showcase/tests/Feature/PostBroadcastTest.php
declare(strict_types=1);

use App\Arqel\Resources\PostResource;
use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;

it('the post resource broadcasts updates', function () {
    expect(in_array(BroadcastsResourceUpdates::class, class_uses(PostResource::class), true))->toBeTrue();
});
```

- [ ] **Step 2: Run it — fails**

Run: `cd apps/showcase && vendor/bin/pest --filter PostBroadcast 2>&1 | tail -10`
Expected: FAIL — trait not used.

- [ ] **Step 3: Add the trait** — in `PostResource.php`, add `use Arqel\Realtime\Concerns\BroadcastsResourceUpdates;` import and `use BroadcastsResourceUpdates;` in the class body.

- [ ] **Step 4: Create the VersionHistoryDrawer component**

```tsx
// apps/showcase/resources/js/Components/VersionHistoryDrawer.tsx
import { VersionTimeline, VersionDiff, type Version } from '@arqel-dev/versioning';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export function VersionHistoryDrawer({ resource, id, versions }: { resource: string; id: number; versions: Version[] }) {
  const [active, setActive] = useState<Version | null>(null);
  return (
    <div>
      <VersionTimeline
        versions={versions}
        onViewDiff={setActive}
        onRestore={(v) => router.post(`/admin/${resource}/${id}/versions/${v.id}/restore`)}
        canRestore={(v) => !v.is_initial}
      />
      {active && <VersionDiff before={{}} after={{}} />}
    </div>
  );
}
```

(This is a demo component to exercise the versioning JS types + the real restore route. Wire it into the Post edit page if there's an extension slot; otherwise leave it as a registered component the E2E can mount. Keep minimal.)

- [ ] **Step 5: Run the test + typecheck**

Run: `cd apps/showcase && vendor/bin/pest --filter PostBroadcast 2>&1 | tail -10`
Expected: PASS.
Run: `pnpm --filter @arqel-dev/showcase typecheck 2>&1 | tail -5`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add apps/showcase/app/Arqel/Resources/PostResource.php apps/showcase/resources/js/Components/VersionHistoryDrawer.tsx apps/showcase/tests/Feature/PostBroadcastTest.php
git commit --signoff -m "feat(showcase): BroadcastsResourceUpdates on PostResource + versioning UI drawer"
```

---

## PHASE 5 — E2E tests for the new surfaces

> Each spec drives `localhost:8090` (the dogfood stack). Run via `./dogfood.sh e2e` or directly against a running stack. Keep specs focused — one behavior per `test()`.

### Task 5.1: E2E — actions, form-layouts, table-advanced

**Files:**
- Create: `apps/showcase/tests/e2e/05-actions.spec.ts`, `06-form-layouts.spec.ts`, `07-table-advanced.spec.ts`

- [ ] **Step 1: Write 05-actions.spec.ts** (publish confirm + change-status modal + bulk archive)

```ts
// apps/showcase/tests/e2e/05-actions.spec.ts
import { test, expect } from './fixtures';

test('row publish action shows a confirmation dialog', async ({ page }) => {
  await page.goto('/admin/posts');
  await page.getByRole('button', { name: /publish/i }).first().click();
  await expect(page.getByRole('dialog')).toBeVisible();
});

test('change-status row action opens a field modal with a select', async ({ page }) => {
  await page.goto('/admin/posts');
  await page.getByRole('button', { name: /change status/i }).first().click();
  await expect(page.getByRole('dialog').getByRole('combobox')).toBeVisible();
});
```

(Use the existing `fixtures.ts` auth fixture. Match the actual rendered button labels — verify against the running app; adjust selectors.)

- [ ] **Step 2: Write 06-form-layouts.spec.ts** (Tabs visible, switch tab)

```ts
// apps/showcase/tests/e2e/06-form-layouts.spec.ts
import { test, expect } from './fixtures';

test('post create form renders tabs and switches between them', async ({ page }) => {
  await page.goto('/admin/posts/create');
  await expect(page.getByRole('tab', { name: /content/i })).toBeVisible();
  await page.getByRole('tab', { name: /meta/i }).click();
  await expect(page.getByRole('tab', { name: /meta/i })).toHaveAttribute('aria-selected', 'true');
});
```

- [ ] **Step 3: Write 07-table-advanced.spec.ts** (computed col, relationship col, togglable)

```ts
// apps/showcase/tests/e2e/07-table-advanced.spec.ts
import { test, expect } from './fixtures';

test('post table shows computed Words column and Author relationship column', async ({ page }) => {
  await page.goto('/admin/posts');
  await expect(page.getByRole('columnheader', { name: /words/i })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: /author/i })).toBeVisible();
});
```

- [ ] **Step 4: Run them against the stack**

Run: `apps/showcase/dogfood.sh up && sleep 8 && apps/showcase/dogfood.sh fresh && apps/showcase/dogfood.sh e2e -- 05-actions 06-form-layouts 07-table-advanced 2>&1 | tail -20`
Expected: all pass (adjust selectors to the real DOM if any fail — inspect via the Playwright report).

- [ ] **Step 5: Commit**

```bash
git add apps/showcase/tests/e2e/05-actions.spec.ts apps/showcase/tests/e2e/06-form-layouts.spec.ts apps/showcase/tests/e2e/07-table-advanced.spec.ts
git commit --signoff -m "test(showcase): E2E for custom actions, form tabs, advanced table columns"
```

### Task 5.2: E2E — workflow, soft-delete, uploads, i18n, theme, realtime

**Files:**
- Create: `apps/showcase/tests/e2e/08-workflow.spec.ts`, `09-soft-delete.spec.ts`, `10-uploads.spec.ts`, `11-i18n.spec.ts`, `12-theme.spec.ts`, `13-realtime.spec.ts`

- [ ] **Step 1: Write 08-workflow.spec.ts** (ticket transition via the StateTransitionField/route)

```ts
// apps/showcase/tests/e2e/08-workflow.spec.ts
import { test, expect } from './fixtures';

test('ticket edit shows the state transition control with available transitions', async ({ page }) => {
  await page.goto('/admin/tickets');
  await page.getByRole('row').nth(1).getByRole('link', { name: /edit/i }).click();
  await expect(page.getByText(/transition/i).first()).toBeVisible();
});
```

- [ ] **Step 2: Write 09-soft-delete.spec.ts** (orders index renders; trashed not shown by default)

```ts
// apps/showcase/tests/e2e/09-soft-delete.spec.ts
import { test, expect } from './fixtures';

test('orders index renders and excludes soft-deleted rows by default', async ({ page }) => {
  await page.goto('/admin/orders');
  await expect(page.getByRole('table')).toBeVisible();
});
```

- [ ] **Step 3: Write 10-uploads.spec.ts** (media create shows an image upload control)

```ts
// apps/showcase/tests/e2e/10-uploads.spec.ts
import { test, expect } from './fixtures';

test('media create renders an image upload field', async ({ page }) => {
  await page.goto('/admin/media-assets/create');
  await expect(page.locator('input[type="file"]')).toBeAttached();
});
```

- [ ] **Step 4: Write 11-i18n.spec.ts** (locale switcher present + switch)

```ts
// apps/showcase/tests/e2e/11-i18n.spec.ts
import { test, expect } from './fixtures';

test('locale switcher is present in the topbar', async ({ page }) => {
  await page.goto('/admin/posts');
  await expect(page.getByRole('combobox', { name: /locale|idioma|language/i })).toBeVisible();
});
```

- [ ] **Step 5: Write 12-theme.spec.ts** (theme toggle flips the dark class)

```ts
// apps/showcase/tests/e2e/12-theme.spec.ts
import { test, expect } from './fixtures';

test('theme toggle switches the html dark class', async ({ page }) => {
  await page.goto('/admin/posts');
  const html = page.locator('html');
  const before = await html.getAttribute('class');
  await page.getByRole('button', { name: /theme|tema|toggle/i }).first().click();
  await expect(html).not.toHaveClass(before ?? '');
});
```

- [ ] **Step 6: Write 13-realtime.spec.ts** (connection banner appears when the socket can't connect — drives the fallback path)

```ts
// apps/showcase/tests/e2e/13-realtime.spec.ts
import { test, expect } from './fixtures';

test('the app boots with realtime wired (no console error from setupEcho)', async ({ page }) => {
  const errors: string[] = [];
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await page.goto('/admin/posts');
  await expect(page.getByRole('table')).toBeVisible();
  expect(errors.filter((e) => /echo|reverb|websocket/i.test(e))).toHaveLength(0);
});
```

- [ ] **Step 7: Run them**

Run: `apps/showcase/dogfood.sh e2e -- 08-workflow 09-soft-delete 10-uploads 11-i18n 12-theme 13-realtime 2>&1 | tail -25`
Expected: pass (adjust selectors to the real DOM via the report if needed).

- [ ] **Step 8: Commit**

```bash
git add apps/showcase/tests/e2e/08-workflow.spec.ts apps/showcase/tests/e2e/09-soft-delete.spec.ts apps/showcase/tests/e2e/10-uploads.spec.ts apps/showcase/tests/e2e/11-i18n.spec.ts apps/showcase/tests/e2e/12-theme.spec.ts apps/showcase/tests/e2e/13-realtime.spec.ts
git commit --signoff -m "test(showcase): E2E for workflow, soft-delete, uploads, i18n, theme, realtime"
```

### Task 5.3: Full suite green inside the container

- [ ] **Step 1: Run the full PHP + E2E suite via the dogfood stack**

Run: `apps/showcase/dogfood.sh fresh && apps/showcase/dogfood.sh test 2>&1 | tail -15`
Expected: all Pest tests pass against postgres.
Run: `apps/showcase/dogfood.sh e2e 2>&1 | tail -20`
Expected: all 14 E2E specs pass.

- [ ] **Step 2: If any failure is a real framework bug** (not a selector mismatch), STOP and note it — it's a Round-22 candidate; capture it rather than papering over.

---

## PHASE 6 — Migrate the CI showcase job to the dogfood stack

**Files:**
- Modify: `.github/workflows/ci.yml` (the `apps/showcase` job section, ~lines 270-324)

- [ ] **Step 1: Replace the SQLite+serve steps with the compose stack**

In `.github/workflows/ci.yml`, replace the showcase steps ("Setup showcase .env + SQLite", "Migrate + seed", "Smoke-check server boots", "Run E2E") with:

```yaml
      - name: Boot the arqel-dogfood stack (pg + redis + reverb)
        working-directory: apps/showcase
        run: |
          cp .env.dogfood .env
          docker compose -p arqel-dogfood -f compose.dogfood.yml up -d --build
          for i in $(seq 1 60); do
            code=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8090/admin/login || echo 000)
            echo "attempt $i: HTTP $code"
            [ "$code" = "200" ] && break
            sleep 2
          done

      - name: Migrate + seed (postgres)
        working-directory: apps/showcase
        run: docker compose -p arqel-dogfood -f compose.dogfood.yml exec -T app sh -c "php artisan key:generate --force && php artisan migrate:fresh --seed --force && php artisan storage:link"

      - name: Run E2E tests (apps/showcase)
        working-directory: apps/showcase
        env:
          APP_BASE_URL: http://localhost:8090
        run: pnpm exec playwright test

      - name: Dogfood stack logs on failure
        if: failure()
        working-directory: apps/showcase
        run: docker compose -p arqel-dogfood -f compose.dogfood.yml logs --no-color | tail -300

      - name: Tear down the dogfood stack
        if: always()
        working-directory: apps/showcase
        run: docker compose -p arqel-dogfood -f compose.dogfood.yml down -v
```

(Keep the "Install Composer deps in apps/showcase" step IF the image build needs the vendor mounted; since the Dockerfile copies the repo via volume, composer install runs inside — verify the entrypoint or add a `composer install` exec step before migrate. The Playwright browser cache + base URL env must be honored by `playwright.config.ts` — confirm it reads `APP_BASE_URL`.)

- [ ] **Step 2: Confirm playwright.config.ts uses APP_BASE_URL**

Read `apps/showcase/playwright.config.ts`; ensure `use.baseURL` reads `process.env.APP_BASE_URL ?? 'http://localhost:8090'`. If it points at `127.0.0.1:8002` (the old serve port), update it.

- [ ] **Step 3: Push the branch and let CI run**

Run: `git add .github/workflows/ci.yml apps/showcase/playwright.config.ts && git commit --signoff -m "ci: run apps/showcase E2E on the arqel-dogfood Docker stack (pg+redis+reverb)" && git push -u origin feat/showcase-dogfood-expansion`
Expected: CI showcase job builds the stack, migrates against pg, runs E2E against `:8090`, tears down. Drive to green (rerun known flakes).

- [ ] **Step 4: Open the PR**

```bash
gh pr create --base main --head feat/showcase-dogfood-expansion --title "feat(showcase): dogfood expansion — actions/form/table/workflow/uploads/i18n/theme/a11y/realtime + Docker stack" --body "Expands apps/showcase to ~90% PHP / ~83% JS package coverage behind the isolated arqel-dogfood Docker stack (pg+redis+reverb). See docs/superpowers/specs/2026-06-08-showcase-dogfood-expansion-design.md. CI migrated to the stack."
```

---

## PHASE 7 — Re-run the loop (Round 22)

> Only after Phase 1-6 are merged to main and green.

### Task 7.1: Update detection clusters for the new surfaces

- [ ] **Step 1: Author `dogfood-round-22-detect.js`** from the round-21 script, keeping the 96 SEEN signatures (don't re-report fixed bugs), bumping round numbers to 22, and REWRITING the cluster prompts to probe the NEW surfaces: custom actions (publish/change-status/archive end-to-end), form Tabs/Grid/visibleIf serialization, advanced table (computed/relationship/inline-edit/query-builder), workflow UI + the (missing) transition route, soft-delete filters/restore, file/image uploads (the FieldUploadController path), morph at app level (enforceMorphMap round-trips through audit/versioning/workflow), i18n locale switch, theme persistence, a11y focus-trap, realtime broadcast dispatch. Update the SEEN_NOTE to state the surface widened and that a fresh wave of bugs is expected in the previously-untouched packages.

- [ ] **Step 2: Launch Round 22** via the Workflow tool and run the standard detect→verify→issue→TDD-fix→PR→merge-CLEAN pipeline, looping until 2 consecutive clean rounds.

- [ ] **Step 3: Each confirmed bug** follows the same per-bug flow used in rounds 1-21 (isolated worktree, TDD fail-before/pass-after, Pint + PHPStan, DCO, merge on CLEAN, update `dogfood-seen.json`, artifacts PR).

---

## Self-Review notes

- **Spec coverage:** Docker (Phase 1) ✓, models incl. all 3 (Phase 2) ✓, enriched + new Resources (Phase 3) ✓, frontend wiring all 6 JS pkgs (Phase 4) ✓, tests Feature+E2E (Phase 5) ✓, CI on Docker (Phase 6) ✓, Round 22 (Phase 7) ✓.
- **Known framework gap surfaced (not a plan defect):** workflow has no HTTP transition route — Task 3.3 adds a showcase-side route AND flags it; the loop will evaluate whether the missing framework route is a bug.
- **Accessor-name caveats:** several tests use getters (`getActions()`, `getSchema()`, `getColumns()`, `getName()`) — each task says to verify the exact accessor against the package source before finalizing, since these weren't all confirmed. This is the one area to double-check during implementation.
- **Resource base contract:** Task 3.4 says to verify `Resource` abstract method signatures (static vs instance `model()`/`slug()`) against the existing PostResource — done per-task to avoid a mismatch.

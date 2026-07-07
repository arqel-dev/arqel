# Database Notifications UI (milestone 0.19) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar a UI de leitura de notificações persistidas do Arqel — sino no topbar (badge read/unread + dropdown) + página de histórico — sobre as `DatabaseNotification` nativas do Laravel.

**Architecture:** Migration `notifications` stock do Laravel (via Spatie `->hasMigration`). `HandleArqelInertiaRequests::share()` ganha uma shared prop `notifications` (unread_count + recentes, closure lazy scoped ao user). `NotificationController` (`@internal`, rotas em `routes/admin.php`) com index/markAsRead/markAllAsRead/destroy escopados a `$user->notifications()->findOrFail` (anti-IDOR). React: `NotificationBell` (dropdown, esqueleto do `UserMenu`) + `ArqelNotificationsPage` (histórico), data flow Inertia partial-reload `only:['notifications']`.

**Tech Stack:** PHP 8.3+, Laravel 12+ (Notifiable/DatabaseNotification nativos), Pest 3, Spatie Laravel Package Tools; React 19 + Inertia 3 + shadcn (DropdownMenu/Badge existentes; ScrollArea via CLI); TypeScript strict; Vitest.

## Global Constraints

- `declare(strict_types=1);` em todo arquivo PHP novo. `final` por default (controllers são `final`).
- Inertia-only (ADR-001); sem fetch libs novas (ADR-016). Mutações via `router.post/delete` com `only:['notifications']`.
- `core` NÃO depende de `table`/`form`/`widgets`/`marketplace`/`tenant`. Notificações são Laravel-native — nenhuma dep nova.
- Mutações do `NotificationController`: `redirect()->route('arqel.notifications.index')->with('success', __('arqel::notifications.<key>'))` — explícito, nunca `back()`.
- Anti-IDOR: toda leitura/mutação por-id via `$request->user()->notifications()->findOrFail($id)` — 404 se de outro dono. Nunca `DatabaseNotification::find($id)` sem escopo.
- ADR-019: feature aditiva = minor; `NotificationController` é `@internal`; `NotificationBell`/`SharedProps.notifications` são superfície pública sob compromisso de estabilidade.
- Docs PT-BR; código inglês. i18n só `en` + `pt_BR` (não há `es`).
- Testes: core PHP ≥90%, ui JS ≥80% (ADR-008).
- Commits: Conventional + DCO `--signoff`. Scope `core` (na allowlist). Subject ≤100 chars. Body ref milestone 0.19. Pint: `new static` sem parênteses.
- Worktree sem node_modules → `git commit --no-verify` (husky/lint-staged/commitlint não rodam local). CI valida.

---

## File Structure

**PHP (`packages/core`):**
- `database/migrations/2026_07_07_000000_create_notifications_table.php` — **novo** — schema stock.
- `src/ArqelServiceProvider.php` — **modificar** — `->hasMigration('2026_07_07_000000_create_notifications_table')`.
- `src/Http/Middleware/HandleArqelInertiaRequests.php` — **modificar** — shared prop `notifications` + método privado `notificationsPayload(?Authenticatable $user): ?array` + const `RECENT_NOTIFICATIONS_LIMIT = 10`.
- `src/Http/Controllers/NotificationController.php` — **novo** — index/markAsRead/markAllAsRead/destroy.
- `routes/admin.php` — **modificar** — 4 rotas sob `web`+`auth`.
- `resources/lang/en/notifications.php` + `resources/lang/pt_BR/notifications.php` — **novos** — flash strings.
- `resources/lang/{en,pt_BR}/arqel.php` — **modificar** — grupo `notifications` (client-side).
- Tests: `tests/Feature/NotificationSharePropTest.php`, `tests/Feature/NotificationControllerTest.php` — **novos**.

**JS (`packages-js`):**
- `types/src/inertia.ts` — **modificar** — `NotificationItem` + `NotificationPayload` + `SharedProps.notifications`.
- `ui/src/shell/NotificationBell.tsx` — **novo**.
- `ui/src/shell/Topbar.tsx` — **modificar** — slot `notifications?: ReactNode`.
- `ui/src/pages/ArqelNotificationsPage.tsx` — **novo** + registro em `ui/src/pages/index.ts` (`'arqel::notifications'`).
- `ui/src/shadcn/ui/scroll-area.tsx` — **novo** (shadcn CLI) se usado.
- Tests: `ui/tests/NotificationBell.test.tsx`, `ui/tests/pages/ArqelNotificationsPage.test.tsx` — **novos**.

---

## Task 1: Migration `notifications` + registro

**Files:**
- Create: `packages/core/database/migrations/2026_07_07_000000_create_notifications_table.php`
- Modify: `packages/core/src/ArqelServiceProvider.php` (`configurePackage`, adicionar `->hasMigration(...)`)
- Test: `packages/core/tests/Feature/NotificationsMigrationTest.php`

**Interfaces:**
- Produces: a tabela `notifications` (schema stock Laravel: `uuid` PK, `type`, morph `notifiable`, `data` text, `read_at` nullable timestamp, timestamps).

- [ ] **Step 0: Confirmar o runner de teste do core**

Run: `ls packages/core/composer.json; grep -m1 pest packages/core/composer.json`
Anote o comando (assume `vendor/bin/pest` a partir de `packages/core`, com vendor instalado via `composer install` se ausente).

- [ ] **Step 1: Escrever o teste que falha**

Create `packages/core/tests/Feature/NotificationsMigrationTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates the notifications table with the Laravel stock schema', function (): void {
    expect(Schema::hasTable('notifications'))->toBeTrue()
        ->and(Schema::hasColumns('notifications', [
            'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at',
        ]))->toBeTrue();
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/pest --filter='creates the notifications table'`
Expected: FAIL — tabela `notifications` não existe (migration ainda não registrada).

> Nota: o testbench do pacote roda as migrations do pacote automaticamente ao registrar via `->hasMigration`. Se a suíte não rodar migrations do pacote por default, o teste do Step 1 já falha por ausência da tabela — o que é o RED esperado.

- [ ] **Step 3: Criar a migration (schema stock)**

Create `packages/core/database/migrations/2026_07_07_000000_create_notifications_table.php`:

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
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

- [ ] **Step 4: Registrar via Spatie no provider**

Em `packages/core/src/ArqelServiceProvider.php`, no `configurePackage()`, adicionar após `->hasRoute('admin')`:

```php
            ->hasMigration('2026_07_07_000000_create_notifications_table')
```

- [ ] **Step 5: Rodar e ver passar**

Run: `vendor/bin/pest --filter='creates the notifications table'`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add packages/core/database/migrations/2026_07_07_000000_create_notifications_table.php packages/core/src/ArqelServiceProvider.php packages/core/tests/Feature/NotificationsMigrationTest.php
git commit --signoff --no-verify -m "feat(core): add notifications table migration" -m "Laravel stock DatabaseNotification schema. Milestone 0.19."
```

---

## Task 2: Shared prop `notifications`

**Files:**
- Modify: `packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php`
- Test: `packages/core/tests/Feature/NotificationSharePropTest.php`

**Interfaces:**
- Consumes: `notifications` table (Task 1); `$user->notifications()`/`unreadNotifications()` (Notifiable).
- Produces:
  - const `private const int RECENT_NOTIFICATIONS_LIMIT = 10;`
  - `private function notificationsPayload(?Authenticatable $user): ?array` — retorna `null` sem user, senão `['unread_count' => int, 'recent' => array<int, array{id,type,data,read_at,created_at}>]`.
  - shared key `'notifications' => fn () => $this->notificationsPayload($user)` em `share()`.

- [ ] **Step 1: Escrever os testes que falham**

Create `packages/core/tests/Feature/NotificationSharePropTest.php` (padrão de `TenantSharePropTest`: reflexão sobre o método privado):

```php
<?php

declare(strict_types=1);

use Arqel\Core\Http\Middleware\HandleArqelInertiaRequests;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

/** User Notifiable de teste, tabela in-memory. */
final class NotifiableUserForShare extends AuthUser
{
    use Notifiable;

    protected $table = 'users';
    protected $guarded = [];
}

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }
});

it('emits null notifications when there is no authenticated user', function (): void {
    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'notificationsPayload');
    $ref->setAccessible(true);

    expect($ref->invoke($mw, null))->toBeNull();
});

it('emits unread_count and recent items for an authenticated user', function (): void {
    $user = NotifiableUserForShare::query()->create(['name' => 'Ada']);
    // Duas notificações: uma não-lida, uma lida.
    $user->notifications()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => 'App\\Notifications\\Welcome',
        'data' => ['title' => 'Bem-vinda'],
        'read_at' => null,
    ]);
    $user->notifications()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => 'App\\Notifications\\Old',
        'data' => ['title' => 'Antiga'],
        'read_at' => now(),
    ]);

    $mw = new HandleArqelInertiaRequests;
    $ref = new ReflectionMethod($mw, 'notificationsPayload');
    $ref->setAccessible(true);

    $payload = $ref->invoke($mw, $user);

    expect($payload['unread_count'])->toBe(1)
        ->and($payload['recent'])->toHaveCount(2)
        ->and($payload['recent'][0])->toHaveKeys(['id', 'type', 'data', 'read_at', 'created_at'])
        ->and($payload['recent'][0]['type'])->toBe('Old'); // class_basename, latest first
});
```

> **Nota implementador:** `$user->notifications()->create([...])` usa o relationship morph do Notifiable; a tabela `notifications` (Task 1) precisa existir na suíte. `data` é castado para array pelo `DatabaseNotification` do Laravel. Confirme que a ordenação `latest()` faz a lida (mais recente) vir primeiro; se os timestamps colidirem no teste, ordene explicitamente por `created_at` desc e ajuste a asserção.

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/pest --filter='emits unread_count'`
Expected: FAIL — `notificationsPayload` não existe (ReflectionException).

- [ ] **Step 3: Implementar no middleware**

Em `packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php`:

Adicionar a const no topo da classe (perto de outras consts, se houver, senão logo após a abertura da classe):

```php
    private const int RECENT_NOTIFICATIONS_LIMIT = 10;
```

Adicionar a chave em `share()` (dentro do `array_merge`, junto às outras closures):

```php
            'notifications' => fn () => $this->notificationsPayload($user),
```

Adicionar o método privado (perto de `currentTenant`):

```php
    /**
     * Payload de notificações persistidas do usuário autenticado:
     * contagem de não-lidas + as N mais recentes (lidas e não-lidas).
     * Retorna null quando não há usuário — nada vaza.
     *
     * @return array{unread_count: int, recent: array<int, array<string, mixed>>}|null
     */
    private function notificationsPayload(?Authenticatable $user): ?array
    {
        if ($user === null || ! method_exists($user, 'notifications')) {
            return null;
        }

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'recent' => $user->notifications()
                ->latest()
                ->limit(self::RECENT_NOTIFICATIONS_LIMIT)
                ->get()
                ->map(static fn ($n): array => [
                    'id' => $n->id,
                    'type' => class_basename($n->type),
                    'data' => $n->data,
                    'read_at' => $n->read_at?->toIso8601String(),
                    'created_at' => $n->created_at->toIso8601String(),
                ])
                ->all(),
        ];
    }
```

> **Nota:** o guard `method_exists($user, 'notifications')` protege contra um user sem o trait `Notifiable` (não quebra o painel — degrada para "sem notificações"). Se preferir, retorne `['unread_count'=>0,'recent'=>[]]` nesse caso; o teste do Step 1 usa um user Notifiable, então `null`-sem-user é o caminho testado. Mantenha `null` para o caso sem-user (consistente com `tenant`).

- [ ] **Step 4: Rodar e ver passar**

Run: `vendor/bin/pest --filter='notifications'`
Expected: PASS (ambos os casos do share).

- [ ] **Step 5: Commit**

```bash
git add packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php packages/core/tests/Feature/NotificationSharePropTest.php
git commit --signoff --no-verify -m "feat(core): share notifications payload as an Inertia prop" -m "unread_count + recent items, lazy + scoped to the authenticated user. Milestone 0.19."
```

---

## Task 3: `NotificationController` + rotas + i18n server-side

**Files:**
- Create: `packages/core/src/Http/Controllers/NotificationController.php`
- Modify: `packages/core/routes/admin.php`
- Create: `packages/core/resources/lang/en/notifications.php`, `packages/core/resources/lang/pt_BR/notifications.php`
- Test: `packages/core/tests/Feature/NotificationControllerTest.php`

**Interfaces:**
- Consumes: `notifications` table (Task 1); `$user->notifications()` (Notifiable).
- Produces rotas nomeadas: `arqel.notifications.index` (GET), `arqel.notifications.read` (POST `{notification}`), `arqel.notifications.read-all` (POST), `arqel.notifications.destroy` (DELETE `{notification}`).

- [ ] **Step 1: Escrever os testes que falham**

Create `packages/core/tests/Feature/NotificationControllerTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class NotifiableUserForController extends AuthUser
{
    use Notifiable;

    protected $table = 'users';
    protected $guarded = [];
}

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->timestamps();
        });
    }
});

function makeNotification(NotifiableUserForController $user, ?\DateTimeInterface $readAt = null): string
{
    $id = (string) Str::uuid();
    $user->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\Welcome',
        'data' => ['title' => 'Olá'],
        'read_at' => $readAt,
    ]);

    return $id;
}

it('marks a notification as read scoped to the owner', function (): void {
    $user = NotifiableUserForController::query()->create(['name' => 'Ada']);
    $id = makeNotification($user);

    $this->actingAs($user)
        ->post("/admin/notifications/{$id}/read")
        ->assertRedirect(route('arqel.notifications.index'));

    expect($user->notifications()->find($id)->read_at)->not->toBeNull();
});

it('returns 404 when marking a notification owned by another user (anti-IDOR)', function (): void {
    $owner = NotifiableUserForController::query()->create(['name' => 'Owner']);
    $attacker = NotifiableUserForController::query()->create(['name' => 'Mallory']);
    $id = makeNotification($owner);

    $this->actingAs($attacker)
        ->post("/admin/notifications/{$id}/read")
        ->assertNotFound();

    expect($owner->notifications()->find($id)->read_at)->toBeNull();
});

it('marks all as read', function (): void {
    $user = NotifiableUserForController::query()->create(['name' => 'Ada']);
    makeNotification($user);
    makeNotification($user);

    $this->actingAs($user)
        ->post('/admin/notifications/read-all')
        ->assertRedirect(route('arqel.notifications.index'));

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('destroys a notification scoped to the owner', function (): void {
    $user = NotifiableUserForController::query()->create(['name' => 'Ada']);
    $id = makeNotification($user);

    $this->actingAs($user)
        ->delete("/admin/notifications/{$id}")
        ->assertRedirect(route('arqel.notifications.index'));

    expect($user->notifications()->find($id))->toBeNull();
});

it('returns 404 when destroying another user notification (anti-IDOR)', function (): void {
    $owner = NotifiableUserForController::query()->create(['name' => 'Owner']);
    $attacker = NotifiableUserForController::query()->create(['name' => 'Mallory']);
    $id = makeNotification($owner);

    $this->actingAs($attacker)
        ->delete("/admin/notifications/{$id}")
        ->assertNotFound();

    expect($owner->notifications()->find($id))->not->toBeNull();
});

it('filters the index to unread only', function (): void {
    $user = NotifiableUserForController::query()->create(['name' => 'Ada']);
    makeNotification($user);              // unread
    makeNotification($user, now());       // read

    $this->actingAs($user)
        ->get('/admin/notifications?filter=unread')
        ->assertOk();
    // Asserção detalhada do payload Inertia fica a cargo do render;
    // o teste-chave é o filtro não quebrar e retornar 200.
});
```

> **Nota implementador:** se o roteamento do painel exigir um middleware/guard específico além de `web`+`auth` para `actingAs` funcionar, siga o padrão de `packages/core/tests/Feature/` que já testa rotas autenticadas (ex.: os testes de `RelationController`/`CommandPalette`). O `index` renderiza uma página Inertia; o teste só assere 200 + (opcional) o componente via `Inertia::assertComponent('arqel::notifications')` se o helper de teste Inertia estiver disponível.

- [ ] **Step 2: Rodar e ver falhar**

Run: `vendor/bin/pest --filter=NotificationControllerTest`
Expected: FAIL — rotas `arqel.notifications.*` não existem (RouteNotFoundException) / 404.

- [ ] **Step 3: Criar o controller**

Create `packages/core/src/Http/Controllers/NotificationController.php`:

```php
<?php

declare(strict_types=1);

namespace Arqel\Core\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @internal Endpoints do sino de notificações. Escopados sempre ao
 * usuário autenticado via `$user->notifications()` (anti-IDOR: um id
 * de outro dono resolve 404 por findOrFail).
 */
final class NotificationController
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);
        $filter = $request->string('filter')->toString();

        $paginator = $user->notifications()
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->through(static fn ($n): array => [
                'id' => $n->id,
                'type' => class_basename($n->type),
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]);

        return Inertia::render('arqel::notifications', [
            'notifications' => $paginator,
            'filter' => $filter === 'unread' ? 'unread' : 'all',
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $this->user($request)->notifications()->findOrFail($notification)->markAsRead();

        return $this->back(__('arqel::notifications.marked_read'));
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications->markAsRead();

        return $this->back(__('arqel::notifications.all_marked_read'));
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->user($request)->notifications()->findOrFail($notification)->delete();

        return $this->back(__('arqel::notifications.deleted'));
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return $user;
    }

    private function back(string $message): RedirectResponse
    {
        return redirect()->route('arqel.notifications.index')->with('success', $message);
    }
}
```

> **Nota:** `string $notification` é o UUID (bind implícito de string, não de model) — o controller resolve sempre via `$user->notifications()->findOrFail`, garantindo escopo. Nunca use route-model-binding de `DatabaseNotification` (bypassa o escopo).

- [ ] **Step 4: Registrar as rotas**

Em `packages/core/routes/admin.php`, adicionar o import e as rotas dentro do grupo `['web','auth']` existente:

```php
use Arqel\Core\Http\Controllers\NotificationController;
```

Dentro de `Route::middleware(['web', 'auth'])->group(function (): void { ... })`, adicionar:

```php
    Route::get('/admin/notifications', [NotificationController::class, 'index'])
        ->name('arqel.notifications.index');
    Route::post('/admin/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->name('arqel.notifications.read-all');
    Route::post('/admin/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->name('arqel.notifications.read');
    Route::delete('/admin/notifications/{notification}', [NotificationController::class, 'destroy'])
        ->name('arqel.notifications.destroy');
```

> Ordem importa: `read-all` antes de `{notification}/read` para o literal não ser capturado pelo wildcard.

- [ ] **Step 5: Criar os lang files server-side**

Create `packages/core/resources/lang/en/notifications.php`:

```php
<?php

declare(strict_types=1);

return [
    'marked_read' => 'Notification marked as read.',
    'all_marked_read' => 'All notifications marked as read.',
    'deleted' => 'Notification deleted.',
];
```

Create `packages/core/resources/lang/pt_BR/notifications.php`:

```php
<?php

declare(strict_types=1);

return [
    'marked_read' => 'Notificação marcada como lida.',
    'all_marked_read' => 'Todas as notificações marcadas como lidas.',
    'deleted' => 'Notificação excluída.',
];
```

- [ ] **Step 6: Rodar e ver passar**

Run: `vendor/bin/pest --filter=NotificationControllerTest`
Expected: PASS (6 casos, incluindo os 2 anti-IDOR 404).

- [ ] **Step 7: Pint + PHPStan sanity**

Run: `vendor/bin/pint packages/core/src/Http/Controllers/NotificationController.php`
Run (se disponível): `vendor/bin/phpstan analyse packages/core/src/Http/Controllers/NotificationController.php --memory-limit=1G`
Expected: Pint limpo; PHPStan sem erros novos.

- [ ] **Step 8: Commit**

```bash
git add packages/core/src/Http/Controllers/NotificationController.php packages/core/routes/admin.php packages/core/resources/lang/en/notifications.php packages/core/resources/lang/pt_BR/notifications.php packages/core/tests/Feature/NotificationControllerTest.php
git commit --signoff --no-verify -m "feat(core): notification controller (index/read/read-all/destroy)" -m "Scoped to the authenticated user (anti-IDOR 404). Milestone 0.19."
```

---

## Task 4: Tipos TS + i18n client-side

**Files:**
- Modify: `packages-js/types/src/inertia.ts`
- Modify: `packages/core/resources/lang/en/arqel.php`, `packages/core/resources/lang/pt_BR/arqel.php`

**Interfaces:**
- Produces: `NotificationItem`, `NotificationPayload` (exportados); `SharedProps.notifications: NotificationPayload | null`.

- [ ] **Step 1: Adicionar os tipos**

Em `packages-js/types/src/inertia.ts`, ao lado de `FlashPayload` (linha ~32), adicionar:

```ts
export interface NotificationItem {
  id: string;
  type: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string;
}

export interface NotificationPayload {
  unread_count: number;
  recent: NotificationItem[];
}
```

Em `SharedProps` (linha ~46), adicionar o campo:

```ts
  notifications: NotificationPayload | null;
```

- [ ] **Step 2: Typecheck**

Run: `pnpm --filter @arqel-dev/types typecheck` (ou o comando de typecheck do pacote; se o worktree não tiver toolchain JS instalada, `pnpm install` primeiro — os testes JS das Tasks 5-6 exigem isso de qualquer modo).
Expected: sem erros de tipo.

- [ ] **Step 3: Adicionar strings client-side em arqel.php**

Em `packages/core/resources/lang/en/arqel.php`, adicionar (ou criar) o grupo `notifications`:

```php
    'notifications' => [
        'title' => 'Notifications',
        'mark_read' => 'Mark as read',
        'mark_all_read' => 'Mark all as read',
        'empty' => 'No notifications',
        'view_all' => 'View all',
    ],
```

Em `packages/core/resources/lang/pt_BR/arqel.php`, o espelho:

```php
    'notifications' => [
        'title' => 'Notificações',
        'mark_read' => 'Marcar como lida',
        'mark_all_read' => 'Marcar todas como lidas',
        'empty' => 'Nenhuma notificação',
        'view_all' => 'Ver todas',
    ],
```

> **Nota:** confirme a estrutura existente de `arqel.php` (é um array aninhado por grupos — ex.: `auth.menu.*` usado no `UserMenu`). Insira `notifications` como um grupo de topo, coerente com o padrão.

- [ ] **Step 4: Commit**

```bash
git add packages-js/types/src/inertia.ts packages/core/resources/lang/en/arqel.php packages/core/resources/lang/pt_BR/arqel.php
git commit --signoff --no-verify -m "feat(core): notification types and client-side i18n strings" -m "NotificationPayload in SharedProps + arqel.notifications.* (en/pt_BR). Milestone 0.19."
```

---

## Task 5: `NotificationBell` + slot no Topbar

**Files:**
- Create: `packages-js/ui/src/shell/NotificationBell.tsx`
- Modify: `packages-js/ui/src/shell/Topbar.tsx` (slot `notifications?`)
- Create: `packages-js/ui/src/shadcn/ui/scroll-area.tsx` (shadcn CLI, se usado)
- Test: `packages-js/ui/tests/NotificationBell.test.tsx`

**Interfaces:**
- Consumes: shared prop `notifications` (Task 2 payload / Task 4 types); `DropdownMenu`/`Badge` (shadcn existentes); `router` do `@inertiajs/react`.
- Produces: `export function NotificationBell(): JSX.Element` — lê `usePage().props.notifications`, renderiza trigger+badge+dropdown.

- [ ] **Step 1: Escrever os testes que falham**

Create `packages-js/ui/tests/NotificationBell.test.tsx` (siga o padrão dos testes existentes em `packages-js/ui/tests/` — confirme como eles mockam `usePage`/`router` do Inertia; ex.: os testes de `UserMenu`):

```tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import { NotificationBell } from '../src/shell/NotificationBell';

// Mock do Inertia — ajuste ao helper de mock já usado nos testes de UserMenu.
const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: mockProps }),
  router: { post: (...a: unknown[]) => post(...a) },
  Link: ({ children }: { children: React.ReactNode }) => <a>{children}</a>,
}));

let mockProps: unknown;

describe('NotificationBell', () => {
  it('shows the unread badge when there are unread notifications', () => {
    mockProps = { notifications: { unread_count: 3, recent: [] } };
    render(<NotificationBell />);
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('hides the badge when unread_count is 0', () => {
    mockProps = { notifications: { unread_count: 0, recent: [] } };
    render(<NotificationBell />);
    expect(screen.queryByText('0')).not.toBeInTheDocument();
  });

  it('renders a notification title from data and marks it read on click', () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [{ id: 'abc', type: 'Welcome', data: { title: 'Olá', action_url: '/x' }, read_at: null, created_at: '2026-07-07T00:00:00Z' }],
      },
    };
    render(<NotificationBell />);
    // abrir o dropdown (o teste pode precisar clicar no trigger dependendo do shadcn DropdownMenu)
    fireEvent.click(screen.getByRole('button'));
    expect(screen.getByText('Olá')).toBeInTheDocument();
  });

  it('renders a graceful fallback when data has no convention keys', () => {
    mockProps = {
      notifications: {
        unread_count: 1,
        recent: [{ id: 'z', type: 'RawThing', data: { foo: 1 }, read_at: null, created_at: '2026-07-07T00:00:00Z' }],
      },
    };
    render(<NotificationBell />);
    fireEvent.click(screen.getByRole('button'));
    expect(screen.getByText(/RawThing/)).toBeInTheDocument();
  });
});
```

> **Nota implementador:** o modo de abrir o `DropdownMenuContent` do shadcn em teste (Radix) pode exigir configuração — replique exatamente o que os testes de `UserMenu.test.tsx` (se existir) fazem. Se o Radix não montar o conteúdo em jsdom, teste o conteúdo renderizando `NotificationBell` com o menu forçado aberto (prop `defaultOpen`) ou extraia a lista para um subcomponente `NotificationList` testável isoladamente e teste-o direto — prefira o subcomponente extraído para robustez.

- [ ] **Step 2: Rodar e ver falhar**

Run: `pnpm --filter @arqel-dev/ui test -- NotificationBell`
Expected: FAIL — módulo `NotificationBell` não existe.

- [ ] **Step 3: (Se usar ScrollArea) adicionar via shadcn CLI**

Se optar por `ScrollArea` para a lista: `pnpm --filter @arqel-dev/ui dlx shadcn@latest add scroll-area` (ou o comando que o projeto usa — ver `packages-js/ui/components.json`). Alternativa (preferir se o CLI causar fricção): um `<div>` com `max-h-*` + `overflow-y-auto` (Tailwind), como o `FlashContainer` fez sem deps. Decida por simplicidade; documente no commit.

- [ ] **Step 4: Implementar `NotificationBell`**

Create `packages-js/ui/src/shell/NotificationBell.tsx` — espelhe a estrutura de `UserMenu.tsx` (mesmo `DropdownMenu`/`DropdownMenuTrigger`/`DropdownMenuContent align="end"`, mesmo uso de `useArqelTranslations` com fallback inline). Requisitos concretos:
- Ler `const { notifications } = usePage().props;` (tipado `NotificationPayload | null`).
- Trigger: botão com ícone `Bell` (lucide-react) + `Badge` mostrando `notifications?.unread_count` **apenas quando `> 0`**. `aria-label={t('arqel.notifications.title','Notifications')}`.
- Content: cabeçalho com `t('arqel.notifications.title')`; se `unread_count > 0`, um item "Marcar todas como lidas" que faz `router.post(route... 'arqel.notifications.read-all', {}, { preserveScroll: true, only: ['notifications'] })` (use a helper de rota do projeto se houver; senão a URL literal `/admin/notifications/read-all`).
- Lista das `recent`: para cada item, renderizar `data.title` (+ `data.body`/`data.message` se houver). Se `data.action_url` (string), envolver num `<Link href={String(data.action_url)}>`. Ícone via `data.icon` se for nome lucide conhecido, senão `Bell` default. Item com `read_at === null` recebe um indicador (dot / classe de fundo via tokens). Clique num item não-lido → `router.post('/admin/notifications/'+id+'/read', {}, { preserveScroll: true, only: ['notifications'] })`.
- Se `recent` vazio: mostrar `t('arqel.notifications.empty','No notifications')`.
- Rodapé: `<Link href="/admin/notifications">{t('arqel.notifications.view_all','View all')}</Link>`.
- **Zero CSS ad-hoc** — só shadcn primitives + classes Tailwind + tokens OKLCH (ver [[feedback_shadcn_project_patterns]]).

Considere extrair a lista para um `NotificationList` interno (facilita o teste e mantém o arquivo focado).

- [ ] **Step 5: Adicionar o slot no Topbar**

Em `packages-js/ui/src/shell/Topbar.tsx`, adicionar à interface de props `notifications?: ReactNode;` e renderizá-lo na região direita (perto de `userMenu`/`tenantSwitcher`), na ordem visual apropriada (sino antes do userMenu, tipicamente).

- [ ] **Step 6: Rodar e ver passar**

Run: `pnpm --filter @arqel-dev/ui test -- NotificationBell`
Expected: PASS.

- [ ] **Step 7: Typecheck + build**

Run: `pnpm --filter @arqel-dev/ui typecheck`
Expected: sem erros.

- [ ] **Step 8: Commit**

```bash
git add packages-js/ui/src/shell/NotificationBell.tsx packages-js/ui/src/shell/Topbar.tsx packages-js/ui/tests/NotificationBell.test.tsx
# + scroll-area.tsx se adicionado
git commit --signoff --no-verify -m "feat(ui): notification bell with unread badge and dropdown" -m "Reads the notifications shared prop; mark-read via Inertia partial reload. Milestone 0.19."
```

---

## Task 6: `ArqelNotificationsPage` (histórico)

**Files:**
- Create: `packages-js/ui/src/pages/ArqelNotificationsPage.tsx`
- Modify: `packages-js/ui/src/pages/index.ts` (registrar `'arqel::notifications'`)
- Test: `packages-js/ui/tests/pages/ArqelNotificationsPage.test.tsx`

**Interfaces:**
- Consumes: props Inertia `{ notifications: paginator, filter }` do `NotificationController::index` (Task 3).
- Produces: `export default function ArqelNotificationsPage(): JSX.Element` + entrada `'arqel::notifications'` em `arqelPages`.

- [ ] **Step 1: Escrever o teste que falha**

Create `packages-js/ui/tests/pages/ArqelNotificationsPage.test.tsx`:

```tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import ArqelNotificationsPage from '../../src/pages/ArqelNotificationsPage';

const post = vi.fn();
const del = vi.fn();
vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: mockProps }),
  router: { post: (...a: unknown[]) => post(...a), delete: (...a: unknown[]) => del(...a) },
  Link: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a>,
}));

let mockProps: unknown;

describe('ArqelNotificationsPage', () => {
  it('lists notifications and offers per-item actions', () => {
    mockProps = {
      notifications: { data: [{ id: 'a', type: 'Welcome', data: { title: 'Olá' }, read_at: null, created_at: '2026-07-07T00:00:00Z' }], links: [], meta: {} },
      filter: 'all',
    };
    render(<ArqelNotificationsPage />);
    expect(screen.getByText('Olá')).toBeInTheDocument();
  });

  it('shows an empty state when there are no notifications', () => {
    mockProps = { notifications: { data: [], links: [], meta: {} }, filter: 'all' };
    render(<ArqelNotificationsPage />);
    expect(screen.getByText(/No notifications|Nenhuma/)).toBeInTheDocument();
  });
});
```

- [ ] **Step 2: Rodar e ver falhar**

Run: `pnpm --filter @arqel-dev/ui test -- ArqelNotificationsPage`
Expected: FAIL — módulo não existe.

- [ ] **Step 3: Implementar a página**

Create `packages-js/ui/src/pages/ArqelNotificationsPage.tsx` — página que lê `usePage().props.notifications` (paginator Laravel: `{data, links, meta}`) e `filter`. Requisitos:
- Cabeçalho com título (`t('arqel.notifications.title')`) + toggle de filtro all/unread (dois `Link`s para `/admin/notifications?filter=all|unread`, o ativo destacado).
- Lista de `notifications.data`: cada item mostra `data.title`/`data.body` (mesma convenção do sino), timestamp, indicador de lido/não-lido, e ações: "Marcar como lida" (só se não-lida → `router.post('/admin/notifications/'+id+'/read', {}, {preserveScroll:true, only:['notifications']})` — na página, um reload normal também serve; use `only` p/ consistência) e "Excluir" (`router.delete('/admin/notifications/'+id, {preserveScroll:true})`).
- "Marcar todas como lidas" no topo se houver não-lidas.
- Estado vazio: `t('arqel.notifications.empty')`.
- Paginação: renderizar `notifications.links` (padrão Laravel) como `Link`s Inertia — reusar o componente de paginação do painel se existir (ver `ArqelIndexPage` para o padrão); senão, links simples.
- Reusar primitivas shadcn; zero CSS ad-hoc.

- [ ] **Step 4: Registrar no `arqelPages`**

Em `packages-js/ui/src/pages/index.ts`, adicionar ao objeto `arqelPages`:

```ts
  'arqel::notifications': () =>
    import('./ArqelNotificationsPage.js') as Promise<{ default: ComponentType<unknown> }>,
```

E o re-export nomeado ao final:

```ts
export { default as ArqelNotificationsPage } from './ArqelNotificationsPage.js';
```

- [ ] **Step 5: Rodar e ver passar**

Run: `pnpm --filter @arqel-dev/ui test -- ArqelNotificationsPage`
Expected: PASS.

- [ ] **Step 6: Typecheck**

Run: `pnpm --filter @arqel-dev/ui typecheck`
Expected: sem erros.

- [ ] **Step 7: Commit**

```bash
git add packages-js/ui/src/pages/ArqelNotificationsPage.tsx packages-js/ui/src/pages/index.ts packages-js/ui/tests/pages/ArqelNotificationsPage.test.tsx
git commit --signoff --no-verify -m "feat(ui): notifications history page" -m "Paginated list with all/unread filter and per-item actions. Milestone 0.19."
```

---

## Task 7: Dogfood ao vivo + E2E + SKILL.md

**Files:**
- Modify: showcase — um seeder/comando que dispara `$user->notify()` para popular notificações; montar `<NotificationBell/>` no topbar do showcase se ainda não montado.
- Create: `apps/showcase/tests/e2e/16-notifications.spec.ts`
- Modify: `packages/core/SKILL.md` (documentar a feature + a convenção de chaves do `data`)

**Interfaces:** consome tudo das Tasks 1-6.

- [ ] **Step 1: Popular notificações no showcase**

Identifique o User model do showcase e como o topbar é montado (`grep -rn "Topbar\|NotificationBell\|notify(" apps/showcase`). Adicione:
1. Uma classe `Notification` Laravel simples no showcase (ex.: `App\Notifications\WelcomeNotification` com `toArray()` retornando `['title'=>..., 'body'=>..., 'action_url'=>...]`) — é código do APP (dogfood), demonstra a convenção.
2. Um seeder ou comando artisan que faz `$user->notify(new WelcomeNotification(...))` algumas vezes (mix lido/não-lido).
3. Montar `notifications={<NotificationBell/>}` no `<Topbar>` do showcase.

- [ ] **Step 2: Subir o stack de dogfood e validar AO VIVO (INSUBSTITUÍVEL)**

Run (raiz do monorepo):
```bash
docker compose -p arqel-dogfood -f apps/showcase/compose.dogfood.yml up -d --build
# composer install no container app; migrate:fresh --seed; app na porta 8090
```
Com o Chrome MCP: navegar a `http://localhost:8090/admin`, logar, e validar:
- o sino mostra o badge com a contagem de não-lidas;
- abrir o sino mostra as notificações recentes com título/link;
- clicar "marcar como lida" → **o badge decrementa sem reload de página** (partial reload `only:['notifications']`);
- "ver todas" → página `/admin/notifications` com a lista + filtro;
- "marcar todas" → badge zera.
Expected: comportamento acima confirmado ao vivo. Bugs de integração (serialização, partial-reload não atualizar o badge, escopo) aparecem AQUI — não nos unit tests.

> Se o badge não decrementar após markAsRead, é bug real: verifique que a resposta redirect + o partial reload `only:['notifications']` de fato re-executa a closure da shared prop (o Inertia partial reload só re-avalia as props nomeadas — confirme que `notifications` está entre elas e que a navegação após o redirect carrega a prop atualizada).

- [ ] **Step 3: Escrever a spec E2E (assere por conteúdo)**

Create `apps/showcase/tests/e2e/16-notifications.spec.ts` — seguindo o padrão das specs E2E existentes (ex.: `15-relation-manager.spec.ts`): login → abrir o sino → assertar que uma notificação semeada específica **aparece** (por texto do título, não count) → marcar como lida → assertar que o badge sumiu/decrementou → "ver todas" → página mostra a notificação → marcar-todas → badge some. Asserções por conteúdo (`locator(...).toHaveText/toBeVisible`), nunca count aritmético frágil.

- [ ] **Step 4: Rodar a spec E2E localmente**

Run: `APP_BASE_URL=http://localhost:8090 pnpm --filter <showcase-e2e> exec playwright test 16-notifications` (ajuste ao comando real de E2E do showcase; ver como as specs 14/15 são rodadas).
Expected: PASS localmente antes de confiar no CI. Um fail que reproduz local = bug real (produto ou spec), não flake Docker Hub.

- [ ] **Step 5: Documentar no SKILL.md**

Em `packages/core/SKILL.md`, adicionar uma subseção "### Database Notifications" (PT-BR): como o app dispara (`$user->notify(new X)` Laravel-native), a **convenção de chaves do `data`** (`title`/`body`/`action_url`/`icon` opcionais — o sino renderiza rico se presentes, fallback se não), e que a UI (sino + página) vem pronta. Mencionar que o controller é `@internal` e a shared prop `notifications` é pública.

- [ ] **Step 6: Commit**

```bash
git add apps/showcase packages/core/SKILL.md
git commit --signoff --no-verify -m "feat(showcase): dogfood notifications + e2e + SKILL docs" -m "WelcomeNotification seeder, NotificationBell in topbar, e2e spec, SKILL.md convention docs. Milestone 0.19."
```

---

## Notas finais de integração (para o orquestrador, não são tasks)

- **NÃO** pushar/PR nas tasks — o orquestrador faz após o review de branch inteira (opus).
- Antes do push: `git merge origin/main` (branch pode ficar stale); validar gates CI localmente — commitlint (subjects ≤100, scope `core`/`showcase`), biome (`biome check packages-js apps` exit 0), Pint (`new static` sem parens), `phpstan`, `pest`, `pnpm typecheck`.
- **Dogfood real (Task 7) é o gate de integração** — não confiar só nos unit tests. Se o Docker der fricção (node_modules root-owned, Docker Hub flaky), a validação manual via Chrome MCP + a spec E2E local cobrem; o E2E no CI pode precisar de rerun (flake Docker Hub ≠ bug).
- Toolchain JS no worktree: `pnpm install` na raiz antes das Tasks 4-6 (typecheck/test JS). Build order types→hooks→react se necessário.
- CHANGELOG e bump de versão são passo de release separado — a entrega abre PR; 0.19.0 sai no fluxo de release já validado.

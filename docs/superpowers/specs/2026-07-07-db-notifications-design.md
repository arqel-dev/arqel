# Database Notifications UI (milestone 0.19) — Design Spec

> **Status:** aprovado (brainstorming 2026-07-07). Fonte para o plano de implementação.
> **Milestone:** 0.19 — fecha a lacuna competitiva #4 (Database Notifications) vs Filament/Nova.
> **Base:** origin/main `87c0324` (após #362 Plugin API + #363 ADR-019 API freeze).

## Objetivo

Entregar a **UI de leitura de notificações persistidas**: um sino no topbar do painel com badge de não-lidas (read/unread) + uma página dedicada de histórico, sobre as `DatabaseNotification` nativas do Laravel. Hoje o Arqel só tem **flash** (toasts efêmeros via `useFlash`); não há nada persistido. O app-consumidor dispara `$user->notify(new SomeNotification)` (Laravel-native); o Arqel fornece a UI + a infra de leitura/gestão.

**Escopo decidido (brainstorming):**
1. **Só UI de leitura + infra** — o Arqel NÃO fornece classes `Notification` prontas. O app usa as do Laravel (`$user->notify()`). Fecha a lacuna porque o que falta é a UI.
2. **Shared prop + partial reload** — `unread_count` + últimas N recentes sempre nas shared props (closure lazy); mutações via `router.post/delete` com `only:['notifications']` (badge atualiza sem reload). 100% Inertia (ADR-001/016).
3. **Render por convenção de chaves opcionais** — o sino lê `title`/`body`/`action_url`/`icon` do `data` (JSON arbitrário do Laravel); fallback gracioso sem elas.
4. **Página "ver todas"** — página Inertia dedicada `/admin/notifications`, paginada, filtro all/unread, ações por item + marcar-todas.
5. **Sem broadcast realtime** → 0.19b.

## Contexto factual (exploração do código)

Greenfield total: zero `DatabaseNotification`/`->notify()`/migration `notifications` hoje (só o trait `Notifiable` stock em `apps/demo/User`, usado pelo password broker).

Reusável como está:
- `HandleArqelInertiaRequests::share()` (`packages/core/src/Http/Middleware/HandleArqelInertiaRequests.php:69-98`) — monta shared props globais com closures lazy (`fn () => ...`), já escopadas a `$request->user()`. Chaves atuais: `auth`, `panel`, `tenant`, `flash`, `translations`, `i18n`, `arqel`, `__devtools`. É onde `notifications` entra.
- `flash` (linhas 85-90) lê `session()->get('success'|...)` — referência do padrão de payload.
- `UserMenu.tsx` (`packages-js/ui/src/shell/UserMenu.tsx:46-144`) — `DropdownMenu`/`DropdownMenuTrigger`/`DropdownMenuContent align="end"` (shadcn vendorizado); trigger com avatar + item `Link` Inertia + `router.post` (logout). É o **esqueleto exato** a copiar para o `NotificationBell`.
- `Topbar.tsx` (`packages-js/ui/src/shell/Topbar.tsx:22-46`) — slots `brand`/`search`/`userMenu`/`tenantSwitcher`. Ganha um slot novo `notifications?: ReactNode`.
- `CommandPaletteController` + `routes/admin.php` (`packages/core/routes/admin.php:19-29`, `Route::middleware(['web','auth'])->group(...)`) — padrão de rota framework-level (não por Resource, sem prefixo de Panel). É o padrão do `NotificationController`.
- `RelationController` (`packages/core/src/Http/Controllers/RelationController.php`) — modelo de controller com mutações via `redirect()->route(...)->with('success', __('arqel::...'))` e escopo anti-IDOR por `findOrFail`.
- `Badge` + `DropdownMenu` já em `packages-js/ui/src/shadcn/ui/`. `ScrollArea` e `Popover` NÃO existem.
- i18n: `packages/core/resources/lang/{en,pt_BR}/*.php` (só **2 locales**, não `es`); `relations.php` é o exemplo de lang server-side; strings client-side em `arqel.php` via `useArqelTranslations()` com fallback inline.
- `FlashPayload`/`SharedProps` em `packages-js/types/src/inertia.ts:32-55` — onde `NotificationPayload` entra.

**Conformidade ADR-019 (mergeado em #363):** feature aditiva = **minor** (§2). O `NotificationController` é `Http\Controllers\*` = **`@internal`** (§1, pode evoluir sem compromisso). A superfície pública nova (`NotificationBell`, `notifications` em `SharedProps`) segue o compromisso de estabilidade; tipar `SharedProps.notifications` alinha-se à direção do §6 (tipar props progressivamente).

**Nota factual (corrige premissa anterior):** `back()` É usado hoje em `ResourceController`/`ProfileController`. Nos endpoints NOVOS deste spec usamos `redirect()->route()` explícito (padrão do `RelationController`, o mais recente) — não porque `back()` seja proibido, mas porque o destino explícito é mais robusto para mutações de notificação.

---

## Seção 1 — Backend PHP (`packages/core`)

**Migration `notifications`** — o schema stock do Laravel (`uuid` PK, `type`, `notifiable` morph, `data` JSON/text, `read_at` nullable, timestamps). Publicada via o mecanismo de migration do core (como as demais migrations do pacote), idempotente. Fonte: `php artisan notifications:table` gera o canônico; transcrevemos esse schema numa migration do core.

**Notifiable** — documentar (SKILL + spec) que o model User do painel usa `Illuminate\Notifications\Notifiable` (Laravel-native). Não é código novo do Arqel. O sino lê `$user->notifications` / `$user->unreadNotifications`.

**Shared prop** — em `HandleArqelInertiaRequests::share()`, campo `notifications` (closure lazy, scoped a `$request->user()`):

```php
'notifications' => fn () => $user ? [
    'unread_count' => $user->unreadNotifications()->count(),
    'recent' => $user->notifications()->latest()->limit(self::RECENT_NOTIFICATIONS_LIMIT)->get()
        ->map(fn (DatabaseNotification $n) => [
            'id' => $n->id,
            'type' => class_basename($n->type),
            'data' => $n->data,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ])->all(),
] : null,
```

- `RECENT_NOTIFICATIONS_LIMIT` = constante da classe (10).
- Guard: sem user autenticado → `null` (não vaza nada; mesmo padrão de `tenant`/`auth`).
- `$user` é resolvido uma vez no topo de `share()` (já é o padrão do método).

**`NotificationController`** (`@internal`, rotas framework-level em `routes/admin.php`, `web`+`auth`, padrão `CommandPaletteController`):

| Verbo | Rota | Ação |
|---|---|---|
| GET | `/admin/notifications` | `index` — página Inertia paginada; filtro `?filter=all\|unread` |
| POST | `/admin/notifications/{notification}/read` | `markAsRead` |
| POST | `/admin/notifications/read-all` | `markAllAsRead` |
| DELETE | `/admin/notifications/{notification}` | `destroy` |

- `index`: `$user->notifications()->when($filter==='unread', fn($q)=>$q->whereNull('read_at'))->paginate()`, serializado para props Inertia (mesma forma de item da shared prop). Renderiza `Inertia::render('arqel::notifications/index', [...])`.
- `markAsRead`: `$user->notifications()->findOrFail($id)->markAsRead()` — **anti-IDOR: `findOrFail` escopado ao user → 404** se a notificação for de outro dono.
- `markAllAsRead`: `$user->unreadNotifications->markAsRead()`.
- `destroy`: `$user->notifications()->findOrFail($id)->delete()`.
- Todas as mutações: `redirect()->route('arqel.notifications.index')->with('success', __('arqel::notifications.<key>'))`.
- `{notification}` é o UUID (string), não um binding de model implícito (evita expor `DatabaseNotification::find` sem escopo). O controller resolve sempre via `$user->notifications()`.

---

## Seção 2 — Frontend React (`packages-js/ui`)

**`NotificationBell`** — irmão do `UserMenu` no `Topbar` (novo slot `notifications?: ReactNode` em `Topbar.tsx`). Sobre `DropdownMenu` (shadcn vendorizado, mesmo padrão do `UserMenu`):
- **Trigger:** botão-ícone `Bell` (lucide) + `Badge` (existente) com `unread_count` quando > 0 (some em 0). `aria-label` traduzido.
- **Content** (`align="end"`): cabeçalho "Notificações" + "Marcar todas como lidas" (só se `unread_count > 0`); lista das `recent` (≤10); rodapé "Ver todas" → `Link` Inertia p/ `/admin/notifications`.
- **Render de item (convenção de chaves opcionais em `data`):** `data.title` + `data.body`/`data.message`; se `data.action_url`, o item é `Link` Inertia clicável; ícone via `data.icon` (nome lucide) com fallback padrão; item não-lido tem indicador visual (dot). Sem chaves convencionais → fallback gracioso (`type` + resumo curto do `data`). Clicar num item não-lido dispara `markAsRead`.

**`NotificationsPage`** — página Inertia (`arqel::notifications/index`): lista paginada (read+unread), filtro all/unread (via `?filter=`), ações por item (marcar-lida/excluir) + marcar-todas. Reusa primitivas shadcn + padrão de página do painel.

**Data flow (Inertia-only):** sino lê `notifications` de `usePage().props` (shared prop). `markAsRead`/`markAllAsRead`/`destroy` via `router.post/delete` com `preserveScroll: true` + `only: ['notifications']` (partial reload — badge/lista atualizam sem recarregar). Zero fetch lib (ADR-016).

**shadcn:** `Badge`/`DropdownMenu` existem. `ScrollArea` **falta** — adicionar via shadcn CLI para a lista scrollável (ou overflow custom como `FlashContainer` fez — decisão no plano, preferir shadcn conforme [[feedback_shadcn_project_patterns]]). `Popover` não necessário.

---

## Seção 3 — Tipos, i18n & testes

**Tipos TS** (`packages-js/types/src/inertia.ts`):

```ts
export interface NotificationItem {
  id: string;
  type: string;
  data: Record<string, unknown>;   // convenção: title/body/action_url/icon opcionais
  read_at: string | null;
  created_at: string;
}
export interface NotificationPayload {
  unread_count: number;
  recent: NotificationItem[];
}
```
+ `notifications: NotificationPayload | null` em `SharedProps`.

**i18n** (`packages/core/resources/lang/{en,pt_BR}/notifications.php` — só esses 2 locales):
- Server-side (flash de mutações): `marked_read`, `all_marked_read`, `deleted`.
- Client-side em `arqel.php` grupo `notifications`: "Notificações", "Marcar como lida", "Marcar todas como lidas", "Nenhuma notificação", "Ver todas" — via `useArqelTranslations()` com fallback inline (padrão `UserMenu`).

**Testes:**

*PHP (Pest, `core`, ≥90%):*
- `HandleArqelInertiaRequests` compartilha `notifications` (unread_count + recent, na forma serializada) scoped ao user; `null` sem auth.
- `NotificationController`:
  - `index` paginado + filtro all/unread (unread só retorna `read_at IS NULL`);
  - `markAsRead` seta `read_at`; **`markAsRead` de notificação de OUTRO user → 404** (anti-IDOR via `findOrFail` escopado);
  - `markAllAsRead` zera unread_count;
  - `destroy` remove; **destroy de outro user → 404**;
  - todas as mutações retornam `redirect()->route('arqel.notifications.index')` com flash.
- Fixtures: um User `Notifiable` + notificações inseridas via `DatabaseNotification` (factory ou insert direto na tabela).

*JS (Vitest, `ui`, ≥80%):*
- `NotificationBell`: badge mostra `unread_count` / some em 0; render rico com `data.title`+`action_url` (Link) vs fallback sem chaves; `markAsRead` dispara `router.post` com `only:['notifications']`; item não-lido tem indicador; "marcar todas" só aparece com unread.
- `NotificationsPage`: lista + filtro all/unread + ações por item.

*E2E (Playwright, dogfood porta 8090) — INSUBSTITUÍVEL aqui (feature com UI real):* seed de notificações no showcase (o app dispara `$user->notify()` num seeder/comando) → abrir o sino (badge com contagem), marcar uma como lida (badge decrementa via partial reload, sem reload de página), "ver todas" → página, marcar-todas (badge zera). Validar ao vivo no browser (Chrome MCP) + rodar a spec Playwright local ANTES do CI. Assere por **conteúdo** (uma notificação específica aparece/some), não count exato.

**Escopo — pacotes afetados:** `core` (migration + `NotificationController` + shared prop + lang + rotas) e `packages-js/{ui,types}` (React + tipos). **Não é pacote novo** → sem os 4 pontos de registro de pacote. `core` não ganha dependência nova. Sem broadcast realtime (→ 0.19b).

---

## Unidades e interfaces (isolamento)

| Unidade | Faz | Depende de |
|---|---|---|
| Migration `notifications` | Cria a tabela stock do Laravel | — |
| `HandleArqelInertiaRequests` (+`notifications`) | Compartilha unread_count + recent, scoped ao user | Notifiable do user |
| `NotificationController` (`@internal`) | index/markAsRead/markAllAsRead/destroy escopados ao user | `$user->notifications()`, rotas |
| `NotificationBell` (React) | Sino no topbar: badge + dropdown + render por convenção | DropdownMenu, Badge, shared prop, router |
| `NotificationsPage` (React) | Página de histórico paginada + filtros + ações | primitivas shadcn, router |
| `NotificationPayload` (TS) | Tipa a shared prop | SharedProps |
| lang `notifications.php` (en+pt_BR) | Strings server + client | — |

Cada unidade tem propósito único e interface bem definida; testável isoladamente. `core` não ganha dependência nova — reusa o shell do painel (middleware + topbar) e o mecanismo nativo de notificações do Laravel.

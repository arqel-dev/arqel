# Real-time collaborative editing (Yjs + Reverb)

This page describes how to enable **multi-user collaborative editing** on Arqel Resource fields — Google Docs / Notion style — using the `arqel-dev/realtime` package on the server and `@arqel-dev/realtime-collab` on the client. The solution combines:

- **Yjs** (CRDT) to resolve concurrent merges without a central coordination server.
- **Laravel Reverb** as the official WebSocket broadcaster.
- **Echo** (client) for subscribing to the private channel.
- Snapshot persistence via REST with optimistic concurrency.

## How it works (TL;DR)

1. Each collaborative `(modelType, modelId, field)` has a **local `Y.Doc`** in every client.
2. Every local change is encoded as a `Uint8Array` (state vector) and propagated to the other clients via the private channel `arqel.collab.{modelType}.{modelId}.{field}` (Reverb).
3. Periodically (debounce 2s default), the client sends a **consolidated snapshot** in base64 to the `POST /admin/{resource}/{id}/collab/{field}` endpoint. The server persists it in `arqel_yjs_documents` (longBlob) and dispatches `Events\YjsUpdateReceived` to sync clients that missed updates due to a reconnect.
4. CRDT guarantees that arrival order doesn't matter: two users typing simultaneously converge to the same state.

## Prerequisites

- Laravel 12+ (tested on 12.x and 13.x).
- `arqel-dev/realtime` installed and booted (already shipped in any project that installed the `arqel-dev/arqel` meta-package).
- Minimum auth and policies setup — the channel applies the `view` Gate on the record.

## Installation

### Server side

```bash
composer require laravel/reverb
php artisan reverb:install
php artisan migrate
```

The `2026_05_06_000000_create_yjs_documents` migration (already in `arqel-dev/realtime`) creates the `arqel_yjs_documents` table with a unique `(model_type, model_id, field)` and a blob for state.

`.env`:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=localhost
REVERB_PORT=8080
```

Start the worker:

```bash
php artisan reverb:start
```

### Client side

```bash
pnpm add @arqel-dev/realtime @arqel-dev/realtime-collab yjs
```

Global Echo setup (once in the Inertia bootstrap):

```ts
import { setupEcho } from '@arqel-dev/realtime';

setupEcho({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT),
  forceTLS: false,
});
```

## Using `<CollabRichTextField>`

```tsx
import { CollabRichTextField } from '@arqel-dev/realtime-collab';

export function PostEditor({ post }: { post: { id: number } }) {
  return (
    <CollabRichTextField
      modelType="App\\Models\\Post"
      modelId={post.id}
      field="body"
      persistUrl={`/admin/posts/${post.id}/collab/body`}
      debounceMs={2000}
      placeholder="Write here…"
    />
  );
}
```

The component:

- Creates a local `Y.Doc` + subscribes to the Echo channel `arqel.collab.App\Models\Post.{id}.body`.
- Hydrates the initial state via `GET persistUrl` (previous snapshot, if any).
- Applies remote updates via `Y.applyUpdate` automatically.
- Debounces `POST persistUrl` while the user types.
- Renders a controlled `<textarea>`. For ProseMirror/TipTap integration, use the hook directly.

## `useYjsCollab` hook

```ts
import { useYjsCollab } from '@arqel-dev/realtime-collab';

const { doc, text, status, applyRemote } = useYjsCollab({
  modelType: 'App\\Models\\Post',
  modelId: 42,
  field: 'body',
  persistUrl: '/admin/posts/42/collab/body',
});
```

`status` transitions through `syncing` → `synced` → `offline` (when `window.Echo` is not available). `applyRemote(update)` accepts `Uint8Array` or a base64 string and dispatches it to the `Y.Doc`.

## Channel authorization

The channel is registered in `arqel-dev/realtime` (`routes/channels.php`):

```php
Broadcast::channel(
    'arqel.collab.{modelType}.{modelId}.{field}',
    fn ($user, string $modelType, $modelId, string $field) =>
        app(AwarenessChannelAuthorizer::class)->authorize($user, $modelType, $modelId, $field),
);
```

`AwarenessChannelAuthorizer`:

1. Resolves `$modelType` to an Eloquent class — directly via FQCN or via `ResourceRegistry::all()` matching by `getModel()`.
2. Loads the record with `Model::query()->find($modelId)`.
3. Checks the `view` Gate (when registered by the app); otherwise allow.
4. Defensive: any `Throwable` or unbound registry returns `false` (deny).

For fine-grained policies, define `view` on your `PostPolicy` and realtime inherits it automatically.

## Optimistic concurrency

The `POST` endpoint accepts `{state, version}`. Logic:

- If `incoming.version >= server.version` → save + increment `version` + dispatch `YjsUpdateReceived`.
- If `incoming.version < server.version` → returns `409 {message, serverVersion}`. The client does a fresh `GET`, applies its local state via `Y.mergeUpdates`, and retries.

## Performance

- **Snapshot debounce** (default 2s) avoids hammering disk. For rare edits, raise to 5s.
- **broadcastWith** sends the full state — for very large documents (>200KB) consider switching to deltas via `y-protocols/sync` + a dedicated Reverb WebSocket per document.
- **Table `arqel_yjs_documents`** uses unique `(model_type, model_id, field)`. Garbage-collect old snapshots via a dedicated job if the table grows.
- **Reverb scaling**: 1 Reverb worker supports ~1000 connections. For more, scale horizontally behind Redis (see `laravel/reverb` docs).
- **Client**: the textarea integration rewrites the entire `Y.Text` on each keystroke (simple). For large editors, integrate `y-prosemirror`, which preserves incrementality.

## Available events

- `Arqel\Realtime\Events\YjsUpdateReceived` — fired on every persisted snapshot. `broadcastAs` = `collab.update`. Use it for server-side integrations (e.g. notify Slack when a doc is edited).

## Tests + mocking

The `arqel-dev/realtime` tests run with `BROADCAST_CONNECTION=null` + `Event::fake()` — you don't need Reverb to test policies/handlers. On the frontend, the hook stays in `offline` status when `window.Echo` is undefined, allowing tests in jsdom without heavy mocking.

## Known limitations

- The current textarea integration rebinds the entire `Y.Text` on each keystroke. For rich editors (ProseMirror/TipTap), use the hook directly + `y-prosemirror`.
- There's no **awareness** yet (remote cursors, selection highlighting). Roadmap: RT-006.
- Reconnects covered via snapshot resync — there may be a 2s window where updates are "lost" on the channel but reappear in the next snapshot.
- The `modelType` in the channel is the Eloquent FQCN — encode it on the client (e.g. `App\\Models\\Post`) to match what `ResourceRegistry` registers.

## Next steps (roadmap)

- **RT-006** — awareness (remote cursors + collaborative presence).
- **RT-006.1** — pre-configured `y-prosemirror` adapter to integrate with `<RichTextField />`.
- **RT-006.2** — automatic garbage collection of old `arqel_yjs_documents`.

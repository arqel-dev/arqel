# Edición colaborativa en tiempo real (Yjs + Reverb)

Esta página describe cómo habilitar **edición colaborativa multi-usuario** en los fields de los Resources de Arqel — al estilo Google Docs / Notion — usando el paquete `arqel-dev/realtime` en el servidor y `@arqel-dev/realtime-collab` en el cliente. La solución combina:

- **Yjs** (CRDT) para resolver merges concurrentes sin un servidor central de coordinación.
- **Laravel Reverb** como el broadcaster oficial de WebSocket.
- **Echo** (cliente) para suscribirse al canal privado.
- Persistencia de snapshots vía REST con concurrencia optimista.

## Cómo funciona (TL;DR)

1. Cada `(modelType, modelId, field)` colaborativo tiene un **`Y.Doc` local** en cada cliente.
2. Cada cambio local se codifica como `Uint8Array` (state vector) y se propaga a los otros clientes vía el canal privado `arqel.collab.{modelType}.{modelId}.{field}` (Reverb).
3. Periódicamente (debounce 2s por defecto), el cliente envía un **snapshot consolidado** en base64 al endpoint `POST /admin/{resource}/{id}/collab/{field}`. El servidor lo persiste en `arqel_yjs_documents` (longBlob) y dispatcha `Events\YjsUpdateReceived` para sincronizar clientes que perdieron updates por reconexión.
4. CRDT garantiza que el orden de llegada no importa: dos usuarios escribiendo simultáneamente convergen al mismo estado.

## Requisitos previos

- Laravel 12+ (testeado en 12.x y 13.x).
- `arqel-dev/realtime` instalado y booteado (ya incluido en cualquier proyecto que instaló el meta-paquete `arqel-dev/arqel`).
- Setup mínimo de auth y policies — el canal aplica el Gate `view` sobre el registro.

## Instalación

### Lado servidor

```bash
composer require laravel/reverb
php artisan reverb:install
php artisan migrate
```

La migración `2026_05_06_000000_create_yjs_documents` (ya en `arqel-dev/realtime`) crea la tabla `arqel_yjs_documents` con un único `(model_type, model_id, field)` y un blob para el state.

`.env`:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=localhost
REVERB_PORT=8080
```

Inicia el worker:

```bash
php artisan reverb:start
```

### Lado cliente

```bash
pnpm add @arqel-dev/realtime @arqel-dev/realtime-collab yjs
```

Setup global de Echo (una vez en el bootstrap de Inertia):

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

## Usando `<CollabRichTextField>`

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

El componente:

- Crea un `Y.Doc` local + se suscribe al canal Echo `arqel.collab.App\Models\Post.{id}.body`.
- Hidrata el estado inicial vía `GET persistUrl` (snapshot anterior, si existe).
- Aplica updates remotas vía `Y.applyUpdate` automáticamente.
- Hace debounce de `POST persistUrl` mientras el usuario escribe.
- Renderiza un `<textarea>` controlado. Para integración con ProseMirror/TipTap, usa el hook directamente.

## Hook `useYjsCollab`

```ts
import { useYjsCollab } from '@arqel-dev/realtime-collab';

const { doc, text, status, applyRemote } = useYjsCollab({
  modelType: 'App\\Models\\Post',
  modelId: 42,
  field: 'body',
  persistUrl: '/admin/posts/42/collab/body',
});
```

`status` transita por `syncing` → `synced` → `offline` (cuando `window.Echo` no está disponible). `applyRemote(update)` acepta `Uint8Array` o un string base64 y lo dispatcha al `Y.Doc`.

## Autorización del canal

El canal está registrado en `arqel-dev/realtime` (`routes/channels.php`):

```php
Broadcast::channel(
    'arqel.collab.{modelType}.{modelId}.{field}',
    fn ($user, string $modelType, $modelId, string $field) =>
        app(AwarenessChannelAuthorizer::class)->authorize($user, $modelType, $modelId, $field),
);
```

`AwarenessChannelAuthorizer`:

1. Resuelve `$modelType` a una clase Eloquent — directamente vía FQCN o vía `ResourceRegistry::all()` haciendo match por `getModel()`.
2. Carga el registro con `Model::query()->find($modelId)`.
3. Chequea el Gate `view` (cuando lo registra la app); si no, permite.
4. Defensivo: cualquier `Throwable` o registry no bound devuelve `false` (deny).

Para policies finas, define `view` en tu `PostPolicy` y realtime lo hereda automáticamente.

## Concurrencia optimista

El endpoint `POST` acepta `{state, version}`. Lógica:

- Si `incoming.version >= server.version` → guarda + incrementa `version` + dispatcha `YjsUpdateReceived`.
- Si `incoming.version < server.version` → devuelve `409 {message, serverVersion}`. El cliente hace un `GET` fresco, aplica su estado local vía `Y.mergeUpdates` y reintenta.

## Performance

- **Debounce de snapshot** (por defecto 2s) evita martillar el disco. Para edits raros, sube a 5s.
- **broadcastWith** envía el state completo — para documentos muy grandes (>200KB) considera cambiar a deltas vía `y-protocols/sync` + un WebSocket Reverb dedicado por documento.
- **Tabla `arqel_yjs_documents`** usa único `(model_type, model_id, field)`. Garbage-collect snapshots viejos vía un job dedicado si la tabla crece.
- **Escalado de Reverb**: 1 worker Reverb soporta ~1000 conexiones. Para más, escala horizontalmente detrás de Redis (ver docs de `laravel/reverb`).
- **Cliente**: la integración con textarea reescribe todo el `Y.Text` en cada tecla (simple). Para editores grandes, integra `y-prosemirror`, que preserva la incrementalidad.

## Eventos disponibles

- `Arqel\Realtime\Events\YjsUpdateReceived` — disparado en cada snapshot persistido. `broadcastAs` = `collab.update`. Úsalo para integraciones del lado servidor (e.g. notificar a Slack cuando un doc se edita).

## Tests + mocking

Los tests de `arqel-dev/realtime` corren con `BROADCAST_CONNECTION=null` + `Event::fake()` — no necesitas Reverb para testear policies/handlers. En el frontend, el hook se queda en estado `offline` cuando `window.Echo` es undefined, permitiendo tests en jsdom sin mocking pesado.

## Limitaciones conocidas

- La integración actual con textarea reBindea todo el `Y.Text` en cada tecla. Para editores ricos (ProseMirror/TipTap), usa el hook directamente + `y-prosemirror`.
- Aún no hay **awareness** (cursores remotos, highlighting de selección). Roadmap: RT-006.
- Reconexiones cubiertas vía resync de snapshot — puede haber una ventana de 2s donde updates se "pierden" en el canal pero reaparecen en el siguiente snapshot.
- El `modelType` en el canal es el FQCN Eloquent — codifícalo en el cliente (e.g. `App\\Models\\Post`) para que matchee con lo que `ResourceRegistry` registra.

## Próximos pasos (roadmap)

- **RT-006** — awareness (cursores remotos + presencia colaborativa).
- **RT-006.1** — adaptador `y-prosemirror` pre-configurado para integrar con `<RichTextField />`.
- **RT-006.2** — garbage collection automática de `arqel_yjs_documents` viejos.

# Edição colaborativa em tempo real (Yjs + Reverb)

Esta página descreve como habilitar **edição colaborativa multi-usuário** em campos de Resources do Arqel — ao estilo Google Docs / Notion — usando o pacote `arqel-dev/realtime` no servidor e `@arqel-dev/realtime-collab` no cliente. A solução combina:

- **Yjs** (CRDT) para resolver merges concorrentes sem servidor de coordenação central.
- **Laravel Reverb** como WebSocket broadcaster oficial.
- **Echo** (cliente) para subscrição ao canal privado.
- Persistência de snapshot via REST com optimistic concurrency.

## Como funciona (TL;DR)

1. Cada `(modelType, modelId, field)` colaborativo tem um **`Y.Doc` local** em cada cliente.
2. Toda mudança local é encodada como `Uint8Array` (state vector) e propagada para os outros clientes via canal privado `arqel.collab.{modelType}.{modelId}.{field}` (Reverb).
3. Periodicamente (debounce 2s default), o cliente envia um **snapshot consolidado** em base64 ao endpoint `POST /admin/{resource}/{id}/collab/{field}`. O servidor persiste em `arqel_yjs_documents` (longBlob) e dispara `Events\YjsUpdateReceived` para sincronizar clientes que perderam updates por reconnect.
4. CRDT garante que ordem de chegada não importa: dois usuários digitando simultaneamente convergem para o mesmo state.

## Pré-requisitos

- Laravel 12+ (testado em 12.x e 13.x).
- `arqel-dev/realtime` instalado e bootado (já vem em qualquer projeto que instalou o meta-package `arqel-dev/framework`).
- Setup mínimo de auth e policies — o canal aplica Gate `view` no record.

## Instalação

### Lado servidor

```bash
composer require laravel/reverb
php artisan reverb:install
php artisan migrate
```

A migration `2026_05_06_000000_create_yjs_documents` (já no `arqel-dev/realtime`) cria a tabela `arqel_yjs_documents` com unique `(model_type, model_id, field)` e blob para state.

`.env`:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=localhost
REVERB_PORT=8080
```

Inicie o worker:

```bash
php artisan reverb:start
```

### Lado cliente

```bash
pnpm add @arqel-dev/realtime @arqel-dev/realtime-collab yjs
```

Setup global de Echo (uma vez no bootstrap do Inertia):

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
      placeholder="Escreva aqui…"
    />
  );
}
```

O componente:

- Cria um `Y.Doc` local + subscreve ao canal Echo `arqel.collab.App\Models\Post.{id}.body`.
- Hidrata o state inicial via `GET persistUrl` (snapshot anterior, se houver).
- Aplica updates remotos via `Y.applyUpdate` automaticamente.
- Faz debounce do `POST persistUrl` enquanto o usuário digita.
- Renderiza um `<textarea>` controlled. Para integração com ProseMirror/TipTap, use o hook diretamente.

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

`status` transita por `syncing` → `synced` → `offline` (quando `window.Echo` não está disponível). `applyRemote(update)` aceita `Uint8Array` ou string base64 e despacha para o `Y.Doc`.

## Channel authorization

O canal é registrado em `arqel-dev/realtime` (`routes/channels.php`):

```php
Broadcast::channel(
    'arqel.collab.{modelType}.{modelId}.{field}',
    fn ($user, string $modelType, $modelId, string $field) =>
        app(AwarenessChannelAuthorizer::class)->authorize($user, $modelType, $modelId, $field),
);
```

`AwarenessChannelAuthorizer`:

1. Resolve `$modelType` para uma classe Eloquent — direto via FQCN ou via `ResourceRegistry::all()` matching por `getModel()`.
2. Carrega o record com `Model::query()->find($modelId)`.
3. Verifica o Gate `view` (quando registrado pela app); senão allow.
4. Defensive: qualquer `Throwable` ou registry unbound retorna `false` (deny).

Para policies finas, defina `view` na sua `PostPolicy` e o realtime herda automaticamente.

## Optimistic concurrency

O endpoint `POST` aceita `{state, version}`. Lógica:

- Se `incoming.version >= server.version` → grava + incrementa `version` + dispara `YjsUpdateReceived`.
- Se `incoming.version < server.version` → retorna `409 {message, serverVersion}`. O cliente faz `GET` fresco, aplica seu state local via `Y.mergeUpdates` e re-tenta.

## Performance

- **Debounce de snapshot** (default 2s) evita martelar o disco. Para edits raros, suba para 5s.
- **broadcastWith** envia state completo — em documentos muito grandes (>200KB) considere migrar para deltas via `y-protocols/sync` + WebSocket Reverb dedicado por documento.
- **Tabela `arqel_yjs_documents`** usa unique `(model_type, model_id, field)`. Garbage-collect snapshots antigos com job dedicado se a tabela crescer.
- **Reverb scaling**: 1 worker Reverb suporta ~1000 conexões. Para mais, escale horizontalmente atrás de Redis (ver `laravel/reverb` docs).
- **Cliente**: a integração textarea reescreve o `Y.Text` inteiro em cada keystroke (simple). Para editores grandes, integre `y-prosemirror` que preserva incrementalidade.

## Eventos disponíveis

- `Arqel\Realtime\Events\YjsUpdateReceived` — disparado em cada snapshot persistido. `broadcastAs` = `collab.update`. Use para integrações server-side (ex.: notificar no Slack quando um doc é editado).

## Tests + mocking

Os testes do `arqel-dev/realtime` rodam com `BROADCAST_CONNECTION=null` + `Event::fake()` — você não precisa de Reverb para testar policies/handlers. No frontend, o hook fica em status `offline` quando `window.Echo` é undefined, permitindo testes em jsdom sem mocking pesado.

## Limitações conhecidas

- A integração textarea atual rebinda o `Y.Text` inteiro a cada keystroke. Para editores ricos (ProseMirror/TipTap), use o hook diretamente + `y-prosemirror`.
- Não há ainda **awareness** (cursores remotos, selection highlighting). Roadmap: RT-006.
- Reconnects cobertos via snapshot resync — pode haver janela de 2s onde updates são "perdidos" no canal mas reaparecerão no próximo snapshot.
- O `modelType` no canal é o FQCN da Eloquent — encode-o no client (ex.: `App\\Models\\Post`) para bater com o que o `ResourceRegistry` registra.

## Próximos passos (roadmap)

- **RT-006** — awareness (cursores remotos + presence colaborativa).
- **RT-006.1** — `y-prosemirror` adapter pré-configurado para integrar com `<RichTextField />`.
- **RT-006.2** — garbage collection automático de `arqel_yjs_documents` antigos.

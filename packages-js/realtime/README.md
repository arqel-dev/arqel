# @arqel/realtime

Helper de bootstrap do Laravel Echo para apps Arqel — configura WebSockets
contra um servidor Laravel Reverb.

## Instalação

```bash
pnpm add @arqel/realtime
```

`laravel-echo` e `pusher-js` já vêm como dependências diretas.

## Uso

```ts
// resources/js/app.tsx
import { createArqelApp } from '@arqel/react';
import { setupEcho } from '@arqel/realtime';

setupEcho({
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
});

createArqelApp();
```

Após o `setupEcho`, hooks de `@arqel/hooks` (`useResourceUpdates`,
`useResourcePresence`, etc.) usam o `window.Echo` automaticamente.

## Características

- **Idempotente** — pode ser chamado múltiplas vezes (útil para HMR).
- **SSR-safe** — sem `window`, retorna no-op com warning.
- **Tipos exportados** — `EchoLike`, `EchoChannelLike`,
  `PresenceChannelLike` para uso em hooks customizados.

## Documentação

Veja `SKILL.md` neste pacote e o ticket RT-008 em
`PLANNING/10-fase-3-avancadas.md`.

## Licença

MIT.

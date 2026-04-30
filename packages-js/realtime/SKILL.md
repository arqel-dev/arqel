# SKILL.md — arqel/realtime (JS)

## Purpose

Bootstrap do pipeline realtime do Arqel. Expõe `setupEcho(config)` —
helper que instancia o Laravel Echo apontando para o broadcaster
**Reverb** e anexa Pusher no `window`. É a única peça que conhece os
pacotes `laravel-echo` e `pusher-js`; consumidores (apps Inertia, hooks
em `@arqel/hooks`) lidam apenas com o tipo `EchoLike`.

O pacote **não** re-exporta hooks do `@arqel/hooks`. Mantemos separado
para code-splitting: quem só precisa de tipos não baixa Echo.

## Key Contracts

```ts
import { setupEcho } from '@arqel/realtime';

setupEcho({
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
});
```

Tipos públicos exportados:

- `EchoConfig` — shape aceito por `setupEcho`.
- `EchoLike` — subset da instância Echo consumida pelos hooks.
- `EchoChannelLike`, `PresenceChannelLike`, `PresenceMember` — formas
  de canal que hooks (`useResourceUpdates`, `useResourcePresence`,
  `useActionProgress`, `useWidgetRealtime`) podem assumir.
- `EchoEventListener`, `EchoConnectorLike` — auxiliares.

## Conventions

- **Idempotente**: chamar `setupEcho` duas vezes não recria a instância
  e emite `console.warn`. Útil para HMR.
- **SSR-safe**: em ambientes sem `window` (Node SSR), retorna sem efeito
  com warning — não lança.
- **Broadcaster fixo em `'reverb'`**: o helper foi desenhado para
  Reverb (que fala protocolo Pusher). Para usar Pusher hospedado direto,
  consumidor pode instanciar Echo manualmente — `@arqel/realtime` é
  intencionalmente opinionated.
- **Sem hooks aqui**: hooks moram em `@arqel/hooks` para permitir que o
  bundle do app só importe `setupEcho` se quiser.
- TypeScript strict, sem `any` (apenas asserts pontuais nos pontos onde
  o tipo do `laravel-echo` é overloaded e não casa com `EchoConfig`).
- Lint: Biome (`pnpm lint`); testes: Vitest; build: tsup.

## Examples

```tsx
// resources/js/app.tsx
import { createArqelApp } from '@arqel/react';
import { setupEcho } from '@arqel/realtime';

setupEcho({
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
  forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
});

createArqelApp();
```

Consumir o `window.Echo` em um hook custom usando os tipos publicados:

```ts
import type { EchoLike } from '@arqel/realtime';

function getEcho(): EchoLike | null {
  if (typeof window === 'undefined') return null;
  return (window as unknown as { Echo?: EchoLike }).Echo ?? null;
}
```

## Anti-patterns

- Chamar `setupEcho` dentro de componente React — deve ser invocado
  uma única vez no bootstrap (`app.tsx`).
- Importar `laravel-echo` ou `pusher-js` diretamente em código de app —
  use o helper. Mantém upgrade path centralizado.
- Re-exportar hooks aqui — quebra code-splitting; hooks ficam em
  `@arqel/hooks`.
- Trocar o `broadcaster` para algo diferente de `'reverb'` neste helper —
  se precisar Pusher direto, instancie Echo manualmente.

## Related

- Ticket: `PLANNING/10-fase-3-avancadas.md` → RT-008.
- Hooks consumidores: `packages-js/hooks/src/useResource*` e
  `useAction*`.
- PHP backend: `packages/realtime` (RT-001 — broadcasting service
  provider + Reverb config).

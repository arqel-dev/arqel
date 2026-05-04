import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { EchoConfig, EchoLike } from './types';

/**
 * Bootstrapa Laravel Echo + Pusher no `window`, configurado para o broadcaster
 * Reverb (que fala o protocolo Pusher). Idempotente: chamadas subsequentes
 * com `window.Echo` já populado são silenciosamente ignoradas (warning).
 *
 * Em ambiente SSR (sem `window`), retorna sem efeito — útil para builds
 * Inertia que renderizam server-side.
 *
 * Uso típico em `resources/js/app.tsx`:
 *
 * ```ts
 * setupEcho({
 *   key: import.meta.env.VITE_REVERB_APP_KEY,
 *   wsHost: import.meta.env.VITE_REVERB_HOST,
 *   wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
 *   wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
 *   forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
 * });
 * ```
 */
export function setupEcho(config: EchoConfig): void {
  if (typeof window === 'undefined') {
    // SSR: nada a fazer. Não usamos console.error pois não é erro real.
    console.warn('[arqel-dev/realtime] setupEcho called in SSR context — skipping.');
    return;
  }

  const w = window as unknown as {
    Echo?: EchoLike;
    Pusher?: typeof Pusher;
  };

  if (w.Echo) {
    console.warn('[arqel-dev/realtime] window.Echo already initialized — skipping setupEcho.');
    return;
  }

  // pusher-js anexa-se ao `window.Pusher` para uso interno do laravel-echo.
  w.Pusher = Pusher;

  const echoOptions = {
    broadcaster: 'reverb' as const,
    ...config,
  };

  // O construtor do Echo aceita o objeto de opções diretamente; o tipo do
  // pacote `laravel-echo` é overloaded e não casa exatamente com nosso
  // `EchoConfig`, então fazemos cast seguro para o subset que documentamos.
  // biome-ignore lint/suspicious/noExplicitAny: laravel-echo overloads não casam com nosso EchoConfig
  const instance = new Echo(echoOptions as any);

  w.Echo = instance as unknown as EchoLike;
}

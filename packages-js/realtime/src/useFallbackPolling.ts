import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export interface UseFallbackPollingOptions {
  /** Quando `false` (default), o hook não faz nada. */
  enabled?: boolean;
  /** Intervalo entre `router.reload`s. Default 30s. */
  intervalMs?: number;
  /** Lista de props parciais para `router.reload({ only })`. */
  only?: string[];
}

const DEFAULT_INTERVAL_MS = 30_000;

/**
 * Hook que dispara `router.reload({ only })` periodicamente enquanto
 * `enabled === true`. Pensado para ser ativado quando o WebSocket está
 * desconectado, dando ao usuário um fallback de "near-realtime".
 *
 * SSR-safe: no-op quando `window` não existe.
 */
export function useFallbackPolling(options: UseFallbackPollingOptions = {}): void {
  const { enabled = false, intervalMs = DEFAULT_INTERVAL_MS, only } = options;
  // Serializa `only` para estabilidade no array de dependências —
  // arrays inline criam nova referência a cada render. Em vez de listar
  // `only` (que causaria re-execução indevida), listamos apenas `onlyKey`
  // e reconstruímos a lista a partir dele dentro do efeito.
  const onlyKey = only ? only.join(',') : '';

  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (!enabled) return;

    const onlyList = onlyKey ? onlyKey.split(',') : [];

    const interval = setInterval(() => {
      const reloadOptions: { only?: string[] } = {};
      if (onlyList.length > 0) {
        reloadOptions.only = onlyList;
      }
      router.reload(reloadOptions);
    }, intervalMs);

    return () => {
      clearInterval(interval);
    };
  }, [enabled, intervalMs, onlyKey]);
}

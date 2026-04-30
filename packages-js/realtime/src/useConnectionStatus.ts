import { useEffect, useRef, useState } from 'react';
import type { EchoLike } from './types';

/**
 * Estados possíveis da conexão WebSocket reportados pelo Pusher protocol
 * (Reverb fala o mesmo protocolo).
 *
 * - `connected`    — handshake OK, recebendo eventos.
 * - `connecting`   — tentando conectar (incluindo reconnect attempts).
 * - `disconnected` — perdeu conexão temporariamente; Pusher tentará reconectar.
 * - `failed`       — falhou definitivamente; navegador/transporte incompatível.
 * - `unavailable`  — `window.Echo` ausente (SSR ou pacote não bootstrapado).
 */
export type ConnectionStatus =
  | 'connected'
  | 'connecting'
  | 'disconnected'
  | 'failed'
  | 'unavailable';

export interface ConnectionStatusValue {
  status: ConnectionStatus;
  /** Timestamp (ms) da última transição para `connected`. */
  lastConnectedAt: number | null;
  /** Quantas vezes a conexão saiu de `connected` desde o mount. */
  retryCount: number;
}

interface PusherConnectionLike {
  bind: (event: string, callback: (data?: unknown) => void) => void;
  unbind?: (event: string, callback?: (data?: unknown) => void) => void;
  state?: string;
}

interface WindowWithEcho {
  Echo?: EchoLike;
}

function readEcho(): EchoLike | null {
  if (typeof window === 'undefined') return null;
  const w = window as unknown as WindowWithEcho;
  return w.Echo ?? null;
}

function readConnection(echo: EchoLike | null): PusherConnectionLike | null {
  // biome-ignore lint/suspicious/noExplicitAny: pusher-js connection é genuinamente untyped no shape público
  const connection = (echo?.connector as any)?.pusher?.connection;
  if (!connection || typeof connection.bind !== 'function') return null;
  return connection as PusherConnectionLike;
}

const POLL_INTERVAL_MS = 250;
const POLL_TIMEOUT_MS = 5000;

/**
 * Hook React que expõe o status atual da conexão WebSocket.
 *
 * SSR-safe: sem `window` ou sem `window.Echo`, retorna `'unavailable'`
 * permanente. Quando `window.Echo` aparece tarde (boot async), o hook
 * faz polling curto (≤5s) para se inscrever assim que disponível.
 */
export function useConnectionStatus(): ConnectionStatusValue {
  const [value, setValue] = useState<ConnectionStatusValue>(() => {
    if (typeof window === 'undefined') {
      return { status: 'unavailable', lastConnectedAt: null, retryCount: 0 };
    }
    const echo = readEcho();
    const connection = readConnection(echo);
    const initialState = connection?.state ?? 'connecting';
    return {
      status: initialState === 'connected' ? 'connected' : 'connecting',
      lastConnectedAt: initialState === 'connected' ? Date.now() : null,
      retryCount: 0,
    };
  });

  const valueRef = useRef(value);
  valueRef.current = value;

  useEffect(() => {
    if (typeof window === 'undefined') return;

    let disposed = false;
    let pollTimer: ReturnType<typeof setInterval> | null = null;
    let pollDeadline: ReturnType<typeof setTimeout> | null = null;
    let connection: PusherConnectionLike | null = null;

    const handlers: Array<{ event: string; cb: (data?: unknown) => void }> = [];

    const transition = (next: ConnectionStatus) => {
      setValue((prev) => {
        // Conta retry quando saímos de `connected` para qualquer coisa não-conectada.
        const wasConnected = prev.status === 'connected';
        const stillNotConnected = next !== 'connected';
        const incrementRetry = wasConnected && stillNotConnected;
        return {
          status: next,
          lastConnectedAt: next === 'connected' ? Date.now() : prev.lastConnectedAt,
          retryCount: incrementRetry ? prev.retryCount + 1 : prev.retryCount,
        };
      });
    };

    const subscribe = () => {
      const echo = readEcho();
      const conn = readConnection(echo);
      if (!conn) return false;

      connection = conn;

      const events: ConnectionStatus[] = [
        'connected',
        'connecting',
        'disconnected',
        'failed',
        'unavailable',
      ];

      for (const event of events) {
        const cb = () => {
          if (disposed) return;
          // `unavailable` do Pusher significa "transporte indisponível" — mas
          // mantemos o nosso `'unavailable'` para "Echo ausente". Mapeamos o
          // evento Pusher unavailable para o estado homônimo (mesma semântica
          // de UX: nada a fazer).
          transition(event);
        };
        conn.bind(event, cb);
        handlers.push({ event, cb });
      }

      // Caso já estejamos conectados quando subscrevemos, normaliza o estado.
      if (conn.state === 'connected' && valueRef.current.status !== 'connected') {
        transition('connected');
      }

      return true;
    };

    if (!subscribe()) {
      // Echo ainda não disponível — polling curto até aparecer.
      pollTimer = setInterval(() => {
        if (subscribe() && pollTimer) {
          clearInterval(pollTimer);
          pollTimer = null;
          if (pollDeadline) {
            clearTimeout(pollDeadline);
            pollDeadline = null;
          }
        }
      }, POLL_INTERVAL_MS);

      pollDeadline = setTimeout(() => {
        if (pollTimer) {
          clearInterval(pollTimer);
          pollTimer = null;
        }
        if (!disposed && valueRef.current.status === 'connecting') {
          // Não chegamos a subscrever; degrada para `unavailable`.
          transition('unavailable');
        }
      }, POLL_TIMEOUT_MS);
    }

    return () => {
      disposed = true;
      if (pollTimer) clearInterval(pollTimer);
      if (pollDeadline) clearTimeout(pollDeadline);
      if (connection?.unbind) {
        for (const { event, cb } of handlers) {
          connection.unbind(event, cb);
        }
      }
    };
  }, []);

  return value;
}

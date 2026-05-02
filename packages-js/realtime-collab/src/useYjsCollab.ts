import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import * as Y from 'yjs';
import { decodeUpdate } from './encoders';

export type CollabStatus = 'syncing' | 'synced' | 'offline';

export interface UseYjsCollabOptions {
  modelType: string;
  modelId: string | number;
  field: string;
  /**
   * Optional URL para snapshot persistence. Quando fornecido o hook
   * faz GET inicial para hidratar o Y.Doc.
   */
  persistUrl?: string;
}

export interface UseYjsCollabResult {
  doc: Y.Doc;
  text: Y.Text;
  status: CollabStatus;
  applyRemote: (update: Uint8Array | string) => void;
}

/**
 * Hook que cria um Y.Doc local + binding a um channel Reverb privado.
 *
 * Subscribe defensivo: se `window.Echo` não existir (SSR, ambiente sem
 * setup), o hook fica em status `offline` mas continua funcional como
 * editor local.
 */
export function useYjsCollab(options: UseYjsCollabOptions): UseYjsCollabResult {
  const { modelType, modelId, field, persistUrl } = options;

  const doc = useMemo(() => new Y.Doc(), []);
  const text = useMemo(() => doc.getText('content'), [doc]);
  const [status, setStatus] = useState<CollabStatus>('syncing');
  const mountedRef = useRef(true);

  const applyRemote = useCallback(
    (update: Uint8Array | string): void => {
      const bytes = typeof update === 'string' ? decodeUpdate(update) : update;
      Y.applyUpdate(doc, bytes);
    },
    [doc],
  );

  useEffect(() => {
    mountedRef.current = true;

    // SSR guard
    if (typeof window === 'undefined') {
      setStatus('offline');
      return;
    }

    const echoLike = (window as unknown as { Echo?: EchoLike }).Echo;
    const channelName = `arqel.collab.${modelType}.${modelId}.${field}`;

    // Hidratação inicial via REST (opcional)
    if (typeof persistUrl === 'string' && persistUrl !== '' && typeof fetch === 'function') {
      fetch(persistUrl)
        .then((r) => (r.ok ? r.json() : null))
        .then((payload: unknown) => {
          if (!mountedRef.current || payload === null || typeof payload !== 'object') {
            return;
          }
          const state = (payload as { state?: unknown }).state;
          if (typeof state === 'string' && state !== '') {
            try {
              applyRemote(state);
            } catch {
              // ignore malformed snapshot
            }
          }
        })
        .catch(() => {
          // ignore — fica em modo local
        });
    }

    if (echoLike === undefined) {
      setStatus('offline');
      return;
    }

    const channel = echoLike.private(channelName);
    const listener = (payload: { state?: string }): void => {
      if (typeof payload?.state === 'string') {
        try {
          applyRemote(payload.state);
          if (mountedRef.current) {
            setStatus('synced');
          }
        } catch {
          // ignore malformed
        }
      }
    };

    channel.listen('.collab.update', listener);

    if (mountedRef.current) {
      setStatus('synced');
    }

    return () => {
      mountedRef.current = false;
      try {
        channel.stopListening?.('.collab.update', listener);
      } catch {
        // best-effort cleanup
      }
    };
  }, [modelType, modelId, field, persistUrl, applyRemote]);

  return { doc, text, status, applyRemote };
}

interface EchoChannel {
  listen(event: string, cb: (payload: { state?: string }) => void): EchoChannel;
  stopListening?: (event: string, cb: (payload: { state?: string }) => void) => EchoChannel;
}

interface EchoLike {
  private(channel: string): EchoChannel;
}

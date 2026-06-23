import { useArqelTranslations } from '@arqel-dev/react/utils';
import { Alert, AlertDescription } from '@arqel-dev/ui';
import type { ReactNode } from 'react';
import { type ConnectionStatus, useConnectionStatus } from './useConnectionStatus';
import { useFallbackPolling } from './useFallbackPolling';

export interface ConnectionStatusBannerProps {
  /**
   * Quando `true` e o status é `'disconnected'`, ativa polling Inertia
   * via `router.reload({ only: pollOnly })` enquanto durar o disconnect.
   */
  pollOnDisconnect?: boolean;
  /** Intervalo do fallback polling (default 30s). */
  pollIntervalMs?: number;
  /** Props parciais a recarregar via Inertia. */
  pollOnly?: string[];
  /**
   * Render customizado. Recebe o status atual e o número de retries desde
   * o mount. Quando fornecido, substitui o banner default — mas o componente
   * ainda retorna `null` para `connected` e `unavailable` antes de chamar
   * `renderBanner`.
   */
  renderBanner?: (status: ConnectionStatus, retryCount: number) => ReactNode;
  /** Classe CSS aplicada ao container default. */
  className?: string;
}

/**
 * Maps each connection state to its translation key and the English literal
 * used as a fallback when the key is absent from the shared dictionary (a
 * non-Arqel page or a translation gap). Resolved through
 * `useArqelTranslations()` at render time so the active panel locale applies
 * to this `role=status` / `aria-live` banner.
 */
const STATUS_MESSAGE: Record<ConnectionStatus, { key: string; fallback: string }> = {
  connected: { key: '', fallback: '' },
  connecting: { key: 'arqel.realtime.connecting', fallback: 'Connecting...' },
  disconnected: {
    key: 'arqel.realtime.disconnected',
    fallback: 'Connection lost. Reconnecting...',
  },
  failed: { key: 'arqel.realtime.failed', fallback: 'Connection failed. Refresh page.' },
  unavailable: { key: '', fallback: '' },
};

type AlertVariant = 'default' | 'destructive';

const STATUS_VARIANT: Record<ConnectionStatus, AlertVariant> = {
  connected: 'default',
  connecting: 'default',
  disconnected: 'default',
  failed: 'destructive',
  unavailable: 'default',
};

// Tailwind classes layered on top of the Alert variant to convey
// success/warning intent without extending shadcn's variant set.
const STATUS_TONE: Record<ConnectionStatus, string> = {
  connected: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
  connecting: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
  disconnected: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300',
  failed: '',
  unavailable: '',
};

/**
 * Banner inline que mostra o estado da conexão WebSocket e (opcionalmente)
 * dispara fallback polling Inertia quando o canal cai.
 *
 * Render rules:
 * - `connected`   → null
 * - `unavailable` → null (Echo não configurado; ignora silenciosamente)
 * - outros        → `<Alert>` com variant mapeada por status
 *
 * O fallback polling só é ativado quando `pollOnDisconnect === true` e
 * `status === 'disconnected'`. Em `connecting` / `failed` não polleamos
 * (em `connecting` Pusher já está negociando; em `failed` polling não
 * resolve — usuário precisa refresh).
 */
export function ConnectionStatusBanner(props: ConnectionStatusBannerProps): ReactNode {
  const { pollOnDisconnect, pollIntervalMs, pollOnly, renderBanner, className } = props;
  const { status, retryCount } = useConnectionStatus();
  const t = useArqelTranslations();

  const pollingOptions: {
    enabled: boolean;
    intervalMs?: number;
    only?: string[];
  } = {
    enabled: pollOnDisconnect === true && status === 'disconnected',
  };
  if (pollIntervalMs !== undefined) pollingOptions.intervalMs = pollIntervalMs;
  if (pollOnly !== undefined) pollingOptions.only = pollOnly;
  useFallbackPolling(pollingOptions);

  if (status === 'connected' || status === 'unavailable') {
    return null;
  }

  if (renderBanner) {
    return renderBanner(status, retryCount);
  }

  return (
    <Alert
      variant={STATUS_VARIANT[status]}
      role="status"
      aria-live="polite"
      data-status={status}
      data-arqel-connection-banner=""
      className={[STATUS_TONE[status], className].filter(Boolean).join(' ')}
    >
      <AlertDescription>
        {t(STATUS_MESSAGE[status].key, STATUS_MESSAGE[status].fallback)}
      </AlertDescription>
    </Alert>
  );
}

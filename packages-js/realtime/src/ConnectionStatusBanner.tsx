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

const DEFAULT_MESSAGES: Record<ConnectionStatus, string> = {
  connected: '',
  connecting: 'Connecting...',
  disconnected: 'Connection lost. Reconnecting...',
  failed: 'Connection failed. Refresh page.',
  unavailable: '',
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
      <AlertDescription>{DEFAULT_MESSAGES[status]}</AlertDescription>
    </Alert>
  );
}

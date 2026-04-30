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

/**
 * Banner inline que mostra o estado da conexão WebSocket e (opcionalmente)
 * dispara fallback polling Inertia quando o canal cai.
 *
 * Render rules:
 * - `connected`   → null
 * - `unavailable` → null (Echo não configurado; ignora silenciosamente)
 * - outros        → banner com `role="status"` e `aria-live="polite"`
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
    <div
      role="status"
      aria-live="polite"
      data-status={status}
      className={className ?? 'arqel-connection-banner'}
    >
      {DEFAULT_MESSAGES[status]}
    </div>
  );
}

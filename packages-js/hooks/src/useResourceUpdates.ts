/**
 * `useResourceUpdates` — subscribe to a Laravel Echo private channel for a
 * resource (or specific record) and trigger a partial Inertia reload when an
 * update event arrives.
 *
 * The hook is intentionally defensive: it only activates when `window.Echo` is
 * present at runtime. If Reverb/Echo is not configured in the host app, the
 * hook becomes a no-op so that consumers can ship pages without crashing in
 * environments where realtime is disabled.
 *
 * Note: the `laravel-echo` package is *not* a dependency of `@arqel-dev/hooks` —
 * we only declare an ambient minimal shape of `window.Echo`.
 */

import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * Minimal structural type describing the Echo surface we use. Keeping this
 * narrow avoids a hard dependency on `laravel-echo` types and keeps the hook
 * usable with stubs/mocks.
 */
export interface EchoChannelLike {
  listen(event: string, callback: (payload: ResourceUpdatePayload) => void): EchoChannelLike;
}

export interface EchoLike {
  private(channel: string): EchoChannelLike;
  leave(channel: string): void;
}

declare global {
  interface Window {
    Echo?: EchoLike;
  }
}

/**
 * Shape of the broadcast payload. We type it loosely because broadcasters can
 * include arbitrary metadata, but expose convenience fields used by the
 * default UI integration.
 */
// biome-ignore lint/suspicious/noExplicitAny: payload is intentionally open-ended (broadcast metadata is app-defined).
export type ResourceUpdatePayload = Record<string, any>;

export interface UseResourceUpdatesOptions {
  /**
   * Optional callback invoked on each received update with the raw payload.
   * Useful for showing a toast like "Updated by {user}".
   */
  onUpdate?: (payload: ResourceUpdatePayload) => void;
  /**
   * Override the broadcast event name. Defaults to `.ResourceUpdated`.
   * The leading `.` opts into Laravel's "use literal event name" convention.
   */
  event?: string;
}

const DEFAULT_EVENT = '.ResourceUpdated';

export function useResourceUpdates(
  resourceSlug: string,
  recordId?: string | number,
  options: UseResourceUpdatesOptions = {},
): void {
  const { onUpdate, event = DEFAULT_EVENT } = options;

  useEffect(() => {
    const echo = typeof window !== 'undefined' ? window.Echo : undefined;
    if (!echo) {
      return;
    }

    const channelName =
      recordId !== undefined && recordId !== null
        ? `arqel.${resourceSlug}.${recordId}`
        : `arqel.${resourceSlug}`;

    const only = recordId !== undefined && recordId !== null ? ['record'] : ['records'];

    echo.private(channelName).listen(event, (payload: ResourceUpdatePayload) => {
      if (onUpdate) {
        onUpdate(payload);
      }
      // Note: Inertia v2 reloads always preserve scroll/state by default
      // (see `ReloadOptions` = `Omit<VisitOptions, 'preserveScroll' | 'preserveState'>`).
      router.reload({ only });
    });

    return () => {
      window.Echo?.leave(channelName);
    };
  }, [resourceSlug, recordId, onUpdate, event]);
}

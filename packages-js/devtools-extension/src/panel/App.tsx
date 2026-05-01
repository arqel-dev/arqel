import { InertiaInspector } from './InertiaInspector.js';

export interface AppProps {
  /** Version reported by the on-page Arqel runtime (DEVTOOLS-002). */
  version?: string | null;
}

/**
 * Panel shell rendered inside the "Arqel" DevTools tab.
 *
 * When connected, embeds the `<InertiaInspector />` (DEVTOOLS-003).
 */
export function App({ version = null }: AppProps) {
  const connected = typeof version === 'string' && version.length > 0;
  const label = connected
    ? `Arqel DevTools — Connected (v${version})`
    : 'Arqel DevTools — Inactive';

  return (
    <main data-testid="arqel-devtools-panel" data-status={connected ? 'connected' : 'inactive'}>
      <h1>{label}</h1>
      {connected ? (
        <InertiaInspector />
      ) : (
        <p>Open a page running an Arqel admin panel to activate DevTools.</p>
      )}
    </main>
  );
}

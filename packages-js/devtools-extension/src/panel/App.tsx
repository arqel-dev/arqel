export interface AppProps {
  /** Version reported by the on-page Arqel runtime (DEVTOOLS-002). */
  version?: string | null;
}

/**
 * Panel shell rendered inside the "Arqel" DevTools tab.
 *
 * For DEVTOOLS-001 this is a status indicator only. The real wiring (live
 * detection, Inertia inspector, policy debugger) lands in DEVTOOLS-002+.
 */
export function App({ version = null }: AppProps) {
  const connected = typeof version === 'string' && version.length > 0;
  const label = connected
    ? `Arqel DevTools — Connected (v${version})`
    : 'Arqel DevTools — Inactive';

  return (
    <main data-testid="arqel-devtools-panel" data-status={connected ? 'connected' : 'inactive'}>
      <h1>{label}</h1>
      <p>
        {connected
          ? 'Arqel runtime detected on this page.'
          : 'Open a page running an Arqel admin panel to activate DevTools.'}
      </p>
    </main>
  );
}

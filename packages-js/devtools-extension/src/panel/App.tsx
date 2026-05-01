import { useState } from 'react';
import { type FieldSchema, FieldsInspector } from './FieldsInspector.js';
import { InertiaInspector } from './InertiaInspector.js';
import { PolicyDebugger, type PolicyLogEntry } from './PolicyDebugger.js';

export interface AppProps {
  /** Version reported by the on-page Arqel runtime (DEVTOOLS-002). */
  version?: string | null;
  /**
   * Optional policy log entries (DEVTOOLS-004). Tests inject this to
   * exercise the Policies tab without going through the
   * background-port plumbing.
   */
  policyEntries?: ReadonlyArray<PolicyLogEntry>;
  /**
   * Optional field schema entries (DEVTOOLS-005). Tests inject this
   * to render the Fields inspector without the background-port
   * plumbing.
   */
  fields?: ReadonlyArray<FieldSchema>;
}

type Tab = 'inertia' | 'policies' | 'fields';

/**
 * Panel shell rendered inside the "Arqel" DevTools tab.
 *
 * Top-level tabs:
 *   - Inertia State Inspector (DEVTOOLS-003).
 *   - Policies (DEVTOOLS-004).
 */
export function App({ version = null, policyEntries = [], fields = [] }: AppProps) {
  const connected = typeof version === 'string' && version.length > 0;
  const label = connected
    ? `Arqel DevTools — Connected (v${version})`
    : 'Arqel DevTools — Inactive';

  const [tab, setTab] = useState<Tab>('inertia');

  return (
    <main data-testid="arqel-devtools-panel" data-status={connected ? 'connected' : 'inactive'}>
      <h1>{label}</h1>
      {connected ? (
        <>
          <div role="tablist" className="arqel-top-tabs">
            <button
              type="button"
              role="tab"
              aria-selected={tab === 'inertia'}
              data-testid="top-tab-inertia"
              onClick={() => setTab('inertia')}
            >
              Inertia State Inspector
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={tab === 'policies'}
              data-testid="top-tab-policies"
              onClick={() => setTab('policies')}
            >
              Policies
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={tab === 'fields'}
              data-testid="top-tab-fields"
              onClick={() => setTab('fields')}
            >
              Fields
            </button>
          </div>
          {tab === 'inertia' && <InertiaInspector />}
          {tab === 'policies' && <PolicyDebugger entries={policyEntries} />}
          {tab === 'fields' && <FieldsInspector fields={fields} />}
        </>
      ) : (
        <p>Open a page running an Arqel admin panel to activate DevTools.</p>
      )}
    </main>
  );
}

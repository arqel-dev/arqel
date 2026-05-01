/**
 * Policy debugger panel (DEVTOOLS-004).
 *
 * Renders the `__devtools.policyLog` shared prop emitted by
 * `arqel/core` in `local` environment. Each row is one `Gate::after`
 * event captured server-side: ability, arguments, allow/deny result
 * and a 5-frame stack trace.
 *
 * Production builds never populate the `__devtools` key, so this
 * panel falls back to an empty state — there is no info leak.
 */
import { useMemo, useState } from 'react';

export interface PolicyBacktraceFrame {
  readonly file: string | null;
  readonly line: number | null;
  readonly class: string | null;
  readonly function: string | null;
}

export interface PolicyLogEntry {
  readonly ability: string;
  readonly arguments: ReadonlyArray<unknown>;
  readonly result: boolean;
  readonly backtrace: ReadonlyArray<PolicyBacktraceFrame>;
  readonly timestamp?: number;
}

export interface PolicyDebuggerProps {
  readonly entries: ReadonlyArray<PolicyLogEntry>;
}

type ResultFilter = 'all' | 'allow' | 'deny';

export function PolicyDebugger({ entries }: PolicyDebuggerProps) {
  const [resultFilter, setResultFilter] = useState<ResultFilter>('all');
  const [search, setSearch] = useState('');
  const [expanded, setExpanded] = useState<Set<number>>(new Set());

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    return entries.filter((entry) => {
      if (resultFilter === 'allow' && !entry.result) return false;
      if (resultFilter === 'deny' && entry.result) return false;
      if (term !== '' && !entry.ability.toLowerCase().includes(term)) return false;
      return true;
    });
  }, [entries, resultFilter, search]);

  const counts = useMemo(() => {
    let allow = 0;
    let deny = 0;
    for (const entry of entries) {
      if (entry.result) allow++;
      else deny++;
    }
    return { allow, deny };
  }, [entries]);

  function toggle(index: number) {
    const next = new Set(expanded);
    if (next.has(index)) {
      next.delete(index);
    } else {
      next.add(index);
    }
    setExpanded(next);
  }

  return (
    <div data-testid="arqel-policy-debugger" className="arqel-policy-debugger">
      <header className="arqel-policy-header">
        <span data-testid="policy-counter" className="arqel-policy-counter">
          {counts.allow} allowed / {counts.deny} denied
        </span>
        <div className="arqel-policy-controls">
          <input
            type="search"
            placeholder="Filter ability…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            data-testid="policy-search"
            aria-label="Filter ability"
          />
          <select
            value={resultFilter}
            onChange={(e) => setResultFilter(e.target.value as ResultFilter)}
            data-testid="policy-result-filter"
            aria-label="Filter by result"
          >
            <option value="all">All</option>
            <option value="allow">Allowed</option>
            <option value="deny">Denied</option>
          </select>
        </div>
      </header>

      {filtered.length === 0 ? (
        <p data-testid="policy-empty" className="arqel-policy-empty">
          {entries.length === 0
            ? 'No policy checks recorded for this request.'
            : 'No entries match the current filter.'}
        </p>
      ) : (
        <table data-testid="policy-table" className="arqel-policy-table">
          <thead>
            <tr>
              <th>Ability</th>
              <th>Arguments</th>
              <th>Result</th>
              <th>Stack</th>
            </tr>
          </thead>
          <tbody>
            {filtered.map((entry, idx) => {
              const isOpen = expanded.has(idx);
              return (
                <tr
                  // biome-ignore lint/suspicious/noArrayIndexKey: log entries are append-only and identified by position within a snapshot; ability+idx disambiguates duplicates.
                  key={`${entry.ability}-${idx}`}
                  data-testid="policy-row"
                  data-result={entry.result ? 'allow' : 'deny'}
                >
                  <td className="arqel-policy-ability">{entry.ability}</td>
                  <td className="arqel-policy-arguments">
                    <code>{safeStringify(entry.arguments)}</code>
                  </td>
                  <td>
                    <span
                      className={`arqel-policy-badge arqel-policy-badge--${entry.result ? 'allow' : 'deny'}`}
                      data-testid="policy-result-badge"
                    >
                      {entry.result ? 'allow' : 'deny'}
                    </span>
                  </td>
                  <td>
                    <button
                      type="button"
                      onClick={() => toggle(idx)}
                      data-testid="policy-stack-toggle"
                      aria-expanded={isOpen}
                    >
                      {isOpen ? 'Hide' : 'Show'} ({entry.backtrace.length})
                    </button>
                    {isOpen && (
                      <ol className="arqel-policy-stack" data-testid="policy-stack">
                        {entry.backtrace.map((frame, fIdx) => (
                          // biome-ignore lint/suspicious/noArrayIndexKey: stack frames are positional within an immutable snapshot per entry.
                          <li key={`${idx}-${fIdx}`}>
                            <code>
                              {frame.class ? `${frame.class}::` : ''}
                              {frame.function ?? '<unknown>'}
                            </code>
                            {frame.file && (
                              <span className="arqel-policy-frame-loc">
                                {' '}
                                — {frame.file}
                                {frame.line ? `:${frame.line}` : ''}
                              </span>
                            )}
                          </li>
                        ))}
                      </ol>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}
    </div>
  );
}

function safeStringify(value: unknown): string {
  try {
    return JSON.stringify(value);
  } catch {
    return '[unserialisable]';
  }
}

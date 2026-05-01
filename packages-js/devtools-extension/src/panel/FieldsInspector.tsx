/**
 * Fields schema inspector panel (DEVTOOLS-005).
 *
 * Renders the normalized field schema captured from the inspected
 * page's `pageProps`. Heuristics live in `@arqel/react`'s
 * `extractFieldsSchema()` — this component only consumes the result.
 *
 * Layout:
 *   - Header counter "X visible / Y total".
 *   - Search by name + type filter dropdown.
 *   - Compact list with type badge per row.
 *   - Click on a row expands a detail view showing rules,
 *     dependencies (from `meta.dependsOn`), visibility rule
 *     (`meta.visibleWhen`) and the full meta JSON tree.
 */
import { useMemo, useState } from 'react';
import { JsonNode } from './JsonNode.js';

export interface FieldSchema {
  readonly name: string;
  readonly type: string;
  readonly label?: string;
  readonly required?: boolean;
  readonly visible?: boolean;
  readonly rules?: ReadonlyArray<string>;
  readonly meta?: Readonly<Record<string, unknown>>;
}

export interface FieldsInspectorProps {
  readonly fields: ReadonlyArray<FieldSchema>;
}

export function FieldsInspector({ fields }: FieldsInspectorProps) {
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<string>('all');
  const [expanded, setExpanded] = useState<Set<number>>(new Set());

  const types = useMemo(() => {
    const set = new Set<string>();
    for (const f of fields) set.add(f.type);
    return Array.from(set).sort();
  }, [fields]);

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    return fields
      .map((field, index) => ({ field, index }))
      .filter(({ field }) => {
        if (typeFilter !== 'all' && field.type !== typeFilter) return false;
        if (term !== '' && !field.name.toLowerCase().includes(term)) return false;
        return true;
      });
  }, [fields, search, typeFilter]);

  const visibleCount = useMemo(() => fields.filter((f) => f.visible !== false).length, [fields]);

  function toggle(index: number) {
    const next = new Set(expanded);
    if (next.has(index)) {
      next.delete(index);
    } else {
      next.add(index);
    }
    setExpanded(next);
  }

  if (fields.length === 0) {
    return (
      <div data-testid="arqel-fields-inspector" className="arqel-fields-inspector">
        <p data-testid="fields-empty" className="arqel-fields-empty">
          No fields detected in the current pageProps.
        </p>
      </div>
    );
  }

  return (
    <div data-testid="arqel-fields-inspector" className="arqel-fields-inspector">
      <header className="arqel-fields-header">
        <span data-testid="fields-counter" className="arqel-fields-counter">
          {visibleCount} visible / {fields.length} total
        </span>
        <div className="arqel-fields-controls">
          <input
            type="search"
            placeholder="Filter name…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            data-testid="fields-search"
            aria-label="Filter field name"
          />
          <select
            value={typeFilter}
            onChange={(e) => setTypeFilter(e.target.value)}
            data-testid="fields-type-filter"
            aria-label="Filter by type"
          >
            <option value="all">All types</option>
            {types.map((t) => (
              <option key={t} value={t}>
                {t}
              </option>
            ))}
          </select>
        </div>
      </header>

      <ul className="arqel-fields-list" data-testid="fields-list">
        {filtered.map(({ field, index }) => {
          const isOpen = expanded.has(index);
          const dependsOn = readMetaString(field.meta, 'dependsOn');
          const visibleWhen = field.meta?.['visibleWhen'];
          return (
            <li
              key={`${field.name}-${index}`}
              data-testid="field-row"
              data-type={field.type}
              data-name={field.name}
            >
              <button
                type="button"
                className="arqel-field-summary"
                onClick={() => toggle(index)}
                aria-expanded={isOpen}
                data-testid="field-toggle"
              >
                <span aria-hidden="true">{isOpen ? '▼' : '▶'}</span>
                <span className="arqel-field-name">{field.name}</span>
                <span
                  className={`arqel-field-badge arqel-field-badge--${field.type}`}
                  data-testid="field-type-badge"
                >
                  {field.type}
                </span>
                {field.required && (
                  <span className="arqel-field-required" data-testid="field-required">
                    required
                  </span>
                )}
                {field.visible === false && (
                  <span className="arqel-field-hidden" data-testid="field-hidden">
                    hidden
                  </span>
                )}
                {field.label && <span className="arqel-field-label">— {field.label}</span>}
              </button>
              {isOpen && (
                <div className="arqel-field-detail" data-testid="field-detail">
                  {field.rules && field.rules.length > 0 ? (
                    <section>
                      <h4>Validation rules</h4>
                      <ul data-testid="field-rules">
                        {field.rules.map((rule, rIdx) => (
                          // biome-ignore lint/suspicious/noArrayIndexKey: rules are positional within an immutable schema snapshot.
                          <li key={`${index}-${rIdx}`}>
                            <code>{rule}</code>
                          </li>
                        ))}
                      </ul>
                    </section>
                  ) : (
                    <p className="arqel-field-no-rules">No validation rules.</p>
                  )}
                  {dependsOn !== undefined && (
                    <section>
                      <h4>Depends on</h4>
                      <code data-testid="field-depends-on">{dependsOn}</code>
                    </section>
                  )}
                  {visibleWhen !== undefined && (
                    <section>
                      <h4>Visibility rule</h4>
                      <pre data-testid="field-visible-when">{safeStringify(visibleWhen)}</pre>
                    </section>
                  )}
                  {field.meta && Object.keys(field.meta).length > 0 && (
                    <section>
                      <h4>Meta</h4>
                      <div data-testid="field-meta">
                        <JsonNode value={field.meta} path={`field-${index}-meta`} defaultExpanded />
                      </div>
                    </section>
                  )}
                </div>
              )}
            </li>
          );
        })}
        {filtered.length === 0 && (
          <li className="arqel-fields-empty" data-testid="fields-no-match">
            No fields match the current filter.
          </li>
        )}
      </ul>
    </div>
  );
}

function readMetaString(
  meta: Readonly<Record<string, unknown>> | undefined,
  key: string,
): string | undefined {
  if (!meta) return undefined;
  const value = meta[key];
  return typeof value === 'string' ? value : undefined;
}

function safeStringify(value: unknown): string {
  try {
    return JSON.stringify(value, null, 2);
  } catch {
    return '[unserialisable]';
  }
}

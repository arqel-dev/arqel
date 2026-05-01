/**
 * Recursive JSON tree node used by the Inertia inspector (DEVTOOLS-003).
 *
 * Renders primitives inline and objects/arrays as expandable nodes.
 * Supports an optional `search` filter that highlights matching keys
 * and string values; non-matching nodes are dimmed.
 */
import { useMemo, useState } from 'react';

export interface JsonNodeProps {
  readonly value: unknown;
  readonly nodeKey?: string;
  readonly path?: string;
  readonly search?: string;
  readonly defaultExpanded?: boolean;
}

export function JsonNode({
  value,
  nodeKey,
  path = '$',
  search = '',
  defaultExpanded = true,
}: JsonNodeProps) {
  const [expanded, setExpanded] = useState(defaultExpanded);
  const term = search.trim().toLowerCase();
  const matches = useMemo(() => nodeMatches(nodeKey, value, term), [nodeKey, value, term]);

  if (value === null || typeof value !== 'object') {
    return (
      <div
        data-testid={`json-node-${path}`}
        data-match={term === '' ? 'none' : matches ? 'yes' : 'no'}
        className="arqel-json-node arqel-json-leaf"
      >
        {nodeKey !== undefined && (
          <span className="arqel-json-key">{highlight(nodeKey, term)}: </span>
        )}
        <span className={`arqel-json-value arqel-json-${typeofTag(value)}`}>
          {formatPrimitive(value, term)}
        </span>
      </div>
    );
  }

  const isArray = Array.isArray(value);
  const entries: Array<[string, unknown]> = isArray
    ? (value as unknown[]).map((v, i) => [String(i), v])
    : Object.entries(value as Record<string, unknown>);

  return (
    <div
      data-testid={`json-node-${path}`}
      data-match={term === '' ? 'none' : matches ? 'yes' : 'no'}
      className="arqel-json-node arqel-json-branch"
    >
      <button
        type="button"
        className="arqel-json-toggle"
        onClick={() => setExpanded((e) => !e)}
        aria-expanded={expanded}
        data-testid={`json-toggle-${path}`}
      >
        <span aria-hidden="true">{expanded ? '▼' : '▶'}</span>
        {nodeKey !== undefined && (
          <span className="arqel-json-key">{highlight(nodeKey, term)}</span>
        )}
        <span className="arqel-json-meta">
          {isArray ? `Array(${entries.length})` : `Object{${entries.length}}`}
        </span>
      </button>
      {expanded && (
        <div className="arqel-json-children">
          {entries.map(([k, v]) => (
            <JsonNode
              key={k}
              nodeKey={k}
              value={v}
              path={`${path}.${k}`}
              search={search}
              defaultExpanded={false}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function typeofTag(value: unknown): string {
  if (value === null) return 'null';
  return typeof value;
}

function formatPrimitive(value: unknown, term: string): React.ReactNode {
  if (value === null) return 'null';
  if (typeof value === 'string') return `"${highlight(value, term)}"`;
  if (typeof value === 'undefined') return 'undefined';
  return String(value);
}

function nodeMatches(key: string | undefined, value: unknown, term: string): boolean {
  if (term === '') return true;
  if (key !== undefined && key.toLowerCase().includes(term)) return true;
  if (value === null || typeof value !== 'object') {
    return String(value).toLowerCase().includes(term);
  }
  const entries = Array.isArray(value)
    ? (value as unknown[]).map((v, i) => [String(i), v] as const)
    : Object.entries(value as Record<string, unknown>);
  for (const [k, v] of entries) {
    if (nodeMatches(k, v, term)) return true;
  }
  return false;
}

function highlight(text: string, term: string): React.ReactNode {
  if (term === '') return text;
  const lower = text.toLowerCase();
  const idx = lower.indexOf(term);
  if (idx === -1) return text;
  return (
    <>
      {text.slice(0, idx)}
      <mark data-testid="json-highlight">{text.slice(idx, idx + term.length)}</mark>
      {text.slice(idx + term.length)}
    </>
  );
}

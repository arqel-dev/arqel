/**
 * `<VersionDiff>` — comparador side-by-side de duas snapshots.
 *
 * Recebe dois objetos plain (`before` / `after`) e renderiza um
 * `<dl>` com cada chave como termo (`<dt>`) e duas colunas
 * (`<dd>`) — Before / After. Highlights por status:
 *
 *   added   → bg verde
 *   removed → bg vermelho
 *   changed → bg amarelo
 *   unchanged → default (escondido por padrão; `showUnchanged`).
 *
 * Long text (>100 chars) com mesma quantidade de linhas vira diff
 * linha-a-linha; senão render block-level com badge "Modified".
 */

import { type JSX, useMemo } from 'react';

export type DiffStatus = 'added' | 'removed' | 'changed' | 'unchanged';

export interface DiffEntry {
  key: string;
  status: DiffStatus;
  oldValue: unknown;
  newValue: unknown;
}

export interface VersionDiffProps {
  before: Record<string, unknown>;
  after: Record<string, unknown>;
  fieldLabels?: Record<string, string>;
  showUnchanged?: boolean;
}

const LONG_TEXT_THRESHOLD = 100;

function getDiffEntries(
  before: Record<string, unknown>,
  after: Record<string, unknown>,
): ReadonlyArray<DiffEntry> {
  const keys = new Set<string>([...Object.keys(before), ...Object.keys(after)]);
  const sorted = Array.from(keys).sort();
  return sorted.map<DiffEntry>((key) => {
    const inBefore = Object.hasOwn(before, key);
    const inAfter = Object.hasOwn(after, key);
    const oldValue = inBefore ? before[key] : undefined;
    const newValue = inAfter ? after[key] : undefined;
    let status: DiffStatus;
    if (inBefore && !inAfter) {
      status = 'removed';
    } else if (!inBefore && inAfter) {
      status = 'added';
    } else if (!isEqual(oldValue, newValue)) {
      status = 'changed';
    } else {
      status = 'unchanged';
    }
    return { key, status, oldValue, newValue };
  });
}

function isEqual(a: unknown, b: unknown): boolean {
  if (a === b) {
    return true;
  }
  if (a === null || b === null || typeof a !== typeof b) {
    return false;
  }
  if (typeof a === 'object' && typeof b === 'object') {
    try {
      return JSON.stringify(a) === JSON.stringify(b);
    } catch {
      return false;
    }
  }
  return false;
}

function isPrimitive(v: unknown): v is string | number | boolean | null | undefined {
  return (
    v === null ||
    v === undefined ||
    typeof v === 'string' ||
    typeof v === 'number' ||
    typeof v === 'boolean'
  );
}

function formatPrimitive(v: unknown): string {
  if (v === null) {
    return 'null';
  }
  if (v === undefined) {
    return '';
  }
  if (typeof v === 'boolean') {
    return v ? 'true' : 'false';
  }
  return String(v);
}

interface ValueCellProps {
  value: unknown;
  side: 'before' | 'after';
  status: DiffStatus;
  /** When true, the counterpart value is the same length string and we can do line-by-line. */
  lineDiffWith?: string;
}

function renderValueCell({ value, side, status, lineDiffWith }: ValueCellProps): JSX.Element {
  if (value === undefined && (status === 'added' || status === 'removed')) {
    return (
      <span
        className="arqel-version-diff__value arqel-version-diff__value--missing"
        role="note"
        aria-label={side === 'before' ? 'no previous value' : 'no new value'}
      >
        —
      </span>
    );
  }

  if (!isPrimitive(value)) {
    return (
      <pre
        className="arqel-version-diff__value arqel-version-diff__value--json"
        data-testid={`version-diff-json-${side}`}
      >
        {JSON.stringify(value, null, 2)}
      </pre>
    );
  }

  const text = formatPrimitive(value);
  const isLong = typeof value === 'string' && text.length > LONG_TEXT_THRESHOLD;

  if (isLong) {
    const lines = text.split('\n');
    if (lineDiffWith !== undefined) {
      const otherLines = lineDiffWith.split('\n');
      if (lines.length === otherLines.length) {
        return (
          <div
            className="arqel-version-diff__value arqel-version-diff__value--lines"
            data-testid={`version-diff-lines-${side}`}
          >
            {lines.map((line, idx) => {
              const otherLine = otherLines[idx] ?? '';
              const lineChanged = line !== otherLine;
              const cls = lineChanged
                ? side === 'before'
                  ? 'arqel-version-diff__line arqel-version-diff__line--removed'
                  : 'arqel-version-diff__line arqel-version-diff__line--added'
                : 'arqel-version-diff__line';
              return (
                <span
                  // biome-ignore lint/suspicious/noArrayIndexKey: lines are a stable text snapshot, no reordering possible
                  key={`${side}-${idx}-${line.length}`}
                  className={cls}
                >
                  {line === '' ? ' ' : line}
                </span>
              );
            })}
          </div>
        );
      }
    }
    return (
      <div
        className="arqel-version-diff__value arqel-version-diff__value--block"
        data-testid={`version-diff-block-${side}`}
      >
        <span className="arqel-version-diff__badge">Modified</span>
        <pre className="arqel-version-diff__pre">{text}</pre>
      </div>
    );
  }

  return <span className="arqel-version-diff__value">{text}</span>;
}

export function VersionDiff({
  before,
  after,
  fieldLabels,
  showUnchanged = false,
}: VersionDiffProps): JSX.Element {
  const entries = useMemo(() => getDiffEntries(before, after), [before, after]);
  const visible = useMemo(
    () => (showUnchanged ? entries : entries.filter((e) => e.status !== 'unchanged')),
    [entries, showUnchanged],
  );

  if (visible.length === 0) {
    return (
      <section
        className="arqel-version-diff arqel-version-diff--empty"
        aria-label="Field comparison"
        data-testid="version-diff-empty"
      >
        <p>No changes to display.</p>
      </section>
    );
  }

  return (
    <section
      className="arqel-version-diff"
      aria-label="Field comparison"
      data-testid="version-diff"
    >
      <dl className="arqel-version-diff__list">
        {visible.map((entry) => {
          const label = fieldLabels?.[entry.key] ?? entry.key;
          const rowCls = `arqel-version-diff__row arqel-version-diff__row--${entry.status}`;

          let lineDiffOld: string | undefined;
          let lineDiffNew: string | undefined;
          if (
            entry.status === 'changed' &&
            typeof entry.oldValue === 'string' &&
            typeof entry.newValue === 'string' &&
            entry.oldValue.length > LONG_TEXT_THRESHOLD &&
            entry.newValue.length > LONG_TEXT_THRESHOLD
          ) {
            lineDiffOld = entry.newValue;
            lineDiffNew = entry.oldValue;
          }

          return (
            <div key={entry.key} className={rowCls} data-status={entry.status}>
              <dt className="arqel-version-diff__key">{label}</dt>
              <dd className="arqel-version-diff__cell arqel-version-diff__cell--before">
                {renderValueCell({
                  value: entry.oldValue,
                  side: 'before',
                  status: entry.status,
                  ...(lineDiffOld !== undefined ? { lineDiffWith: lineDiffOld } : {}),
                })}
              </dd>
              <dd className="arqel-version-diff__cell arqel-version-diff__cell--after">
                {renderValueCell({
                  value: entry.newValue,
                  side: 'after',
                  status: entry.status,
                  ...(lineDiffNew !== undefined ? { lineDiffWith: lineDiffNew } : {}),
                })}
              </dd>
            </div>
          );
        })}
      </dl>
    </section>
  );
}

export { getDiffEntries };
export default VersionDiff;

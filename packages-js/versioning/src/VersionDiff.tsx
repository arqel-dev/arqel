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

import { Badge, Card, CardContent } from '@arqel-dev/ui';
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

const STATUS_ROW_BG: Record<DiffStatus, string> = {
  added: 'bg-[var(--chart-2)]/10',
  removed: 'bg-destructive/10',
  changed: 'bg-[var(--chart-4)]/10',
  unchanged: '',
};

function renderValueCell({ value, side, status, lineDiffWith }: ValueCellProps): JSX.Element {
  if (value === undefined && (status === 'added' || status === 'removed')) {
    return (
      <span
        className="text-muted-foreground italic"
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
        className="text-xs whitespace-pre-wrap break-words rounded-sm bg-muted p-2 text-foreground"
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
            className="flex flex-col text-xs font-mono"
            data-testid={`version-diff-lines-${side}`}
          >
            {lines.map((line, idx) => {
              const otherLine = otherLines[idx] ?? '';
              const lineChanged = line !== otherLine;
              const cls = lineChanged
                ? side === 'before'
                  ? 'block px-2 py-0.5 bg-destructive/15 text-foreground'
                  : 'block px-2 py-0.5 bg-[var(--chart-2)]/15 text-foreground'
                : 'block px-2 py-0.5 text-foreground';
              return (
                <span
                  // biome-ignore lint/suspicious/noArrayIndexKey: lines are a stable text snapshot, no reordering possible
                  key={`${side}-${idx}-${line.length}`}
                  className={cls}
                >
                  {line === '' ? ' ' : line}
                </span>
              );
            })}
          </div>
        );
      }
    }
    return (
      <div className="flex flex-col gap-2" data-testid={`version-diff-block-${side}`}>
        <Badge variant="default">Modified</Badge>
        <pre className="text-xs whitespace-pre-wrap break-words rounded-sm bg-muted p-2 text-foreground">
          {text}
        </pre>
      </div>
    );
  }

  return <span className="text-sm text-foreground">{text}</span>;
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
      <Card aria-label="Field comparison" data-testid="version-diff-empty">
        <CardContent className="py-8 text-center text-muted-foreground">
          <p>No changes to display.</p>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card aria-label="Field comparison" data-testid="version-diff">
      <CardContent className="p-0">
        <dl className="flex flex-col divide-y divide-[var(--border)]">
          {visible.map((entry) => {
            const label = fieldLabels?.[entry.key] ?? entry.key;
            const rowCls = `grid grid-cols-[200px_1fr_1fr] gap-4 p-4 ${STATUS_ROW_BG[entry.status]}`;

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
                <dt className="text-sm font-medium text-foreground">{label}</dt>
                <dd className="min-w-0">
                  {renderValueCell({
                    value: entry.oldValue,
                    side: 'before',
                    status: entry.status,
                    ...(lineDiffOld !== undefined ? { lineDiffWith: lineDiffOld } : {}),
                  })}
                </dd>
                <dd className="min-w-0">
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
      </CardContent>
    </Card>
  );
}

export { getDiffEntries };
export default VersionDiff;

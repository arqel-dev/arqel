/**
 * Column cell renderers — one per `ColumnType` discriminant.
 *
 * `<TableCell column={column} value={...} record={...} />` dispatches on
 * `column.type` so DataTable stays free of polymorphic rendering. Cells
 * never reach into the network themselves; everything they need comes
 * from props serialised server-side.
 */

import type {
  BadgeColumnSchema,
  BooleanColumnSchema,
  ColumnSchema,
  ComputedColumnSchema,
  DateColumnSchema,
  IconColumnSchema,
  ImageColumnSchema,
  NumberColumnSchema,
  RelationshipColumnSchema,
  TextColumnSchema,
} from '@arqel-dev/types/tables';
import { cn } from '../utils/cn.js';
import { resolveLucideIcon } from '../utils/icon.js';

export interface CellProps {
  column: ColumnSchema;
  value: unknown;
}

export function TableCell({ column, value }: CellProps) {
  switch (column.type) {
    case 'text':
      return <TextCell column={column} value={value} />;
    case 'badge':
      return <BadgeCell column={column} value={value} />;
    case 'boolean':
      return <BooleanCell column={column} value={value} />;
    case 'date':
      return <DateCell column={column} value={value} />;
    case 'number':
      return <NumberCell column={column} value={value} />;
    case 'icon':
      return <IconCell column={column} />;
    case 'image':
      return <ImageCell column={column} value={value} />;
    case 'relationship':
      return <RelationshipCell column={column} value={value} />;
    case 'computed':
      return <ComputedCell column={column} value={value} />;
  }
}

function asString(value: unknown): string {
  if (value === null || value === undefined) return '';
  if (typeof value === 'string') return value;
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);
  return JSON.stringify(value);
}

function TextCell({ column, value }: { column: TextColumnSchema; value: unknown }) {
  const str = asString(value);
  const truncated =
    column.props.truncate && str.length > column.props.truncate
      ? `${str.slice(0, column.props.truncate)}…`
      : str;
  return (
    <span
      className={cn(
        column.props.weight === 'bold' && 'font-bold',
        column.props.weight === 'medium' && 'font-medium',
      )}
    >
      {truncated}
    </span>
  );
}

/**
 * Colour token → Tailwind classes for `BadgeColumn::colors()`.
 *
 * Tokens mirror the values the PHP producer emits (e.g. `'green'`,
 * `'yellow'`, `'blue'`). Classes are written out in full so Tailwind's
 * JIT can statically discover them. Unknown tokens fall back to muted.
 */
const BADGE_COLOR_CLASS: Record<string, string> = {
  gray: 'bg-muted text-foreground',
  slate: 'bg-slate-100 text-slate-800 dark:bg-slate-800/40 dark:text-slate-200',
  zinc: 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-200',
  red: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
  orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-200',
  amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
  yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200',
  lime: 'bg-lime-100 text-lime-800 dark:bg-lime-900/40 dark:text-lime-200',
  green: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
  emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
  teal: 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-200',
  cyan: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-200',
  sky: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
  blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
  indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
  violet: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
  purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-200',
  fuchsia: 'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-900/40 dark:text-fuchsia-200',
  pink: 'bg-pink-100 text-pink-800 dark:bg-pink-900/40 dark:text-pink-200',
  rose: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
};

const BADGE_MUTED_CLASS = 'bg-muted text-foreground';

function BadgeCell({ column, value }: { column: BadgeColumnSchema; value: unknown }) {
  const key = asString(value);
  const option = column.props.options?.find((o) => o.value === value);
  const label = option?.label ?? key;

  const colorToken = column.props.colors?.[key];
  const colorClass = (colorToken && BADGE_COLOR_CLASS[colorToken]) || BADGE_MUTED_CLASS;

  const iconName = column.props.icons?.[key];
  const Icon = iconName ? resolveLucideIcon(iconName) : undefined;

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium',
        column.props.pill ? 'rounded-full' : 'rounded-sm',
        colorClass,
      )}
    >
      {Icon ? <Icon className="size-3 shrink-0" aria-hidden /> : null}
      {label}
    </span>
  );
}

function BooleanCell({ column, value }: { column: BooleanColumnSchema; value: unknown }) {
  const truthy = Boolean(value);
  return (
    <span role="img" aria-label={truthy ? 'true' : 'false'}>
      {truthy ? (column.props.trueIcon ?? '✓') : (column.props.falseIcon ?? '—')}
    </span>
  );
}

function DateCell({ column, value }: { column: DateColumnSchema; value: unknown }) {
  if (!value) return <span className="text-muted-foreground">—</span>;
  const raw = String(value);
  const date = new Date(raw);
  if (Number.isNaN(date.getTime())) return <span>{raw}</span>;
  if (column.props.mode === 'since') {
    return <time dateTime={date.toISOString()}>{formatRelative(date)}</time>;
  }
  if (column.props.mode === 'datetime') {
    return <time dateTime={date.toISOString()}>{date.toLocaleString()}</time>;
  }
  return <time dateTime={date.toISOString()}>{date.toLocaleDateString()}</time>;
}

function formatRelative(date: Date): string {
  const seconds = Math.round((Date.now() - date.getTime()) / 1000);
  if (seconds < 60) return `${seconds}s ago`;
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.round(hours / 24);
  return `${days}d ago`;
}

function NumberCell({ column, value }: { column: NumberColumnSchema; value: unknown }) {
  if (value === null || value === undefined) return <span>—</span>;
  const num = typeof value === 'number' ? value : Number(value);
  if (Number.isNaN(num)) return <span>{asString(value)}</span>;
  const formatted = num.toFixed(column.props.decimals ?? 0);
  const [whole, dec] = formatted.split('.');
  const safeWhole = whole ?? '0';
  const ts = column.props.thousandsSeparator;
  const finalWhole = ts ? safeWhole.replace(/\B(?=(\d{3})+(?!\d))/g, ts) : safeWhole;
  const ds = column.props.decimalSeparator ?? '.';
  return (
    <span>
      {column.props.prefix ?? ''}
      {finalWhole}
      {dec ? `${ds}${dec}` : ''}
      {column.props.suffix ?? ''}
    </span>
  );
}

function IconCell({ column }: { column: IconColumnSchema }) {
  return (
    <span role="img" aria-label={column.props.icon} title={column.props.icon}>
      {column.props.icon}
    </span>
  );
}

function ImageCell({ column, value }: { column: ImageColumnSchema; value: unknown }) {
  const src = asString(value);
  if (!src) return null;
  const size = column.props.size ?? 32;
  return (
    <img
      src={src}
      alt=""
      style={{ width: size, height: size }}
      className={cn(column.props.shape === 'circular' ? 'rounded-full' : 'rounded')}
    />
  );
}

function RelationshipCell({ column, value }: { column: RelationshipColumnSchema; value: unknown }) {
  if (value === null || value === undefined) return <span>—</span>;
  if (typeof value === 'object') {
    const attr = (value as Record<string, unknown>)[column.props.attribute];
    return <span>{asString(attr)}</span>;
  }
  return <span>{asString(value)}</span>;
}

function ComputedCell({ column, value }: { column: ComputedColumnSchema; value: unknown }) {
  if (value === null || value === undefined) {
    return <span className="text-muted-foreground">{column.props.placeholder ?? '—'}</span>;
  }
  return <span>{asString(value)}</span>;
}

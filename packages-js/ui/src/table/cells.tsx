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
} from '@arqel/types/tables';
import { cn } from '../utils/cn.js';

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

function BadgeCell({ column, value }: { column: BadgeColumnSchema; value: unknown }) {
  const option = column.props.options?.find((o) => o.value === value);
  const label = option?.label ?? asString(value);
  return (
    <span
      className={cn(
        'inline-flex items-center px-2 py-0.5 text-xs',
        column.props.pill ? 'rounded-full' : 'rounded-[var(--radius-arqel-sm)]',
        'bg-[var(--color-arqel-muted)] text-[var(--color-arqel-fg)]',
      )}
    >
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
  if (!value) return <span className="text-[var(--color-arqel-muted-fg)]">—</span>;
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
    return (
      <span className="text-[var(--color-arqel-muted-fg)]">{column.props.placeholder ?? '—'}</span>
    );
  }
  return <span>{asString(value)}</span>;
}

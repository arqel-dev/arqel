/**
 * `<KeyValueInput>` — table-like editor for flat key/value maps.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\KeyValueField`
 * (FIELDS-ADV-007). Renders a 2-column grid (key + value) with optional
 * add/remove controls and supports two output shapes via
 * `field.props.asObject`:
 *
 *   - `false` (default) — emits `Array<{ key: string; value: string }>`
 *     preserving order and tolerating duplicate keys.
 *   - `true` — emits `Record<string, string>` collapsing duplicate keys
 *     last-wins.
 *
 * Drag-and-drop reorder (the `reorderable` flag) is intentionally not
 * wired in this scope — the prop is read defensively but the actual
 * dnd-kit integration is deferred to a follow-up ticket.
 */

import type { FieldSchema } from '@arqel/types/fields';
import type { FieldRendererProps } from '@arqel/ui/form';
import { useEffect, useId, useRef, useState } from 'react';

interface KeyValueProps {
  keyLabel: string;
  valueLabel: string;
  keyPlaceholder: string;
  valuePlaceholder: string;
  editableKeys: boolean;
  addable: boolean;
  deletable: boolean;
  reorderable: boolean;
  asObject: boolean;
}

interface InternalItem {
  __id: string;
  key: string;
  value: string;
  /**
   * `true` for rows the user just added in this session — newly-added
   * rows are always editable even when `editableKeys` is `false`. Rows
   * hydrated from the incoming `value` prop start with `__justAdded`
   * set to `false`.
   */
  __justAdded: boolean;
}

const inputClasses =
  'h-9 w-full rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] ' +
  'bg-[var(--color-arqel-bg)] px-3 text-sm text-[var(--color-arqel-fg)] ' +
  'placeholder:text-[var(--color-arqel-muted-fg)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'aria-invalid:border-[var(--color-arqel-destructive)]';

const buttonClasses =
  'inline-flex h-8 items-center justify-center rounded-[var(--radius-arqel-sm)] ' +
  'border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] ' +
  'px-3 text-sm text-[var(--color-arqel-fg)] ' +
  'hover:bg-[var(--color-arqel-muted)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const removeButtonClasses =
  'inline-flex h-9 w-9 items-center justify-center rounded-[var(--radius-arqel-sm)] ' +
  'border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] ' +
  'text-sm text-[var(--color-arqel-fg)] ' +
  'hover:bg-[var(--color-arqel-muted)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

function generateId(): string {
  if (typeof globalThis.crypto !== 'undefined' && typeof globalThis.crypto.randomUUID === 'function') {
    return globalThis.crypto.randomUUID();
  }
  return `kv-${Math.random().toString(36).slice(2)}-${Date.now().toString(36)}`;
}

function readProps(field: FieldSchema): KeyValueProps {
  // The schema's `props` is declared as a generic record at the
  // discriminated-union boundary; defensively narrow each entry.
  const raw = (field.props ?? {}) as Partial<Record<keyof KeyValueProps, unknown>>;
  return {
    keyLabel: typeof raw.keyLabel === 'string' ? raw.keyLabel : 'Key',
    valueLabel: typeof raw.valueLabel === 'string' ? raw.valueLabel : 'Value',
    keyPlaceholder: typeof raw.keyPlaceholder === 'string' ? raw.keyPlaceholder : '',
    valuePlaceholder: typeof raw.valuePlaceholder === 'string' ? raw.valuePlaceholder : '',
    editableKeys: typeof raw.editableKeys === 'boolean' ? raw.editableKeys : true,
    addable: typeof raw.addable === 'boolean' ? raw.addable : true,
    deletable: typeof raw.deletable === 'boolean' ? raw.deletable : true,
    reorderable: typeof raw.reorderable === 'boolean' ? raw.reorderable : false,
    asObject: typeof raw.asObject === 'boolean' ? raw.asObject : false,
  };
}

function hydrate(value: unknown): InternalItem[] {
  if (Array.isArray(value)) {
    return value
      .filter((entry): entry is { key?: unknown; value?: unknown } => typeof entry === 'object' && entry !== null)
      .map((entry) => ({
        __id: generateId(),
        key: typeof entry.key === 'string' ? entry.key : '',
        value: typeof entry.value === 'string' ? entry.value : String(entry.value ?? ''),
        __justAdded: false,
      }));
  }
  if (value && typeof value === 'object') {
    return Object.entries(value as Record<string, unknown>).map(([k, v]) => ({
      __id: generateId(),
      key: k,
      value: typeof v === 'string' ? v : String(v ?? ''),
      __justAdded: false,
    }));
  }
  return [];
}

function emit(items: InternalItem[], asObject: boolean): unknown {
  if (asObject) {
    const out: Record<string, string> = {};
    for (const item of items) {
      out[item.key] = item.value;
    }
    return out;
  }
  return items.map(({ key, value }) => ({ key, value }));
}

function shallowEqualEmit(a: unknown, b: unknown): boolean {
  // Cheap structural compare to detect external resets without
  // pulling in a deep-equal helper. Falls back to JSON when the
  // shapes are simple — items only contain string fields.
  try {
    return JSON.stringify(a) === JSON.stringify(b);
  } catch {
    return false;
  }
}

export function KeyValueInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const props = readProps(field);
  const hasError = errors !== undefined && errors.length > 0;
  const fallbackId = useId();
  const baseId = inputId ?? fallbackId;

  const [items, setItems] = useState<InternalItem[]>(() => hydrate(value));

  // Track the last value we emitted so the sync effect below can
  // distinguish an external reset (parent form reset) from our own
  // bubble-up via `onChange`.
  const lastEmittedRef = useRef<unknown>(emit(items, props.asObject));

  // Sync external value → internal state when the parent rewrites the
  // field (e.g. form reset). We only re-hydrate if the incoming value
  // differs from our last emit to avoid clobbering local edits.
  useEffect(() => {
    const externalShape = (() => {
      if (Array.isArray(value)) {
        return value.map((entry) => {
          const e = entry as { key?: unknown; value?: unknown };
          return {
            key: typeof e?.key === 'string' ? e.key : '',
            value: typeof e?.value === 'string' ? e.value : String(e?.value ?? ''),
          };
        });
      }
      if (value && typeof value === 'object') {
        const obj: Record<string, string> = {};
        for (const [k, v] of Object.entries(value as Record<string, unknown>)) {
          obj[k] = typeof v === 'string' ? v : String(v ?? '');
        }
        return obj;
      }
      return value === null || value === undefined ? (props.asObject ? {} : []) : value;
    })();

    if (!shallowEqualEmit(externalShape, lastEmittedRef.current)) {
      const next = hydrate(value);
      setItems(next);
      lastEmittedRef.current = emit(next, props.asObject);
    }
    // We intentionally exclude `props.asObject` — switching it shouldn't
    // re-hydrate from `value`, only re-emit (handled below).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value]);

  const updateItems = (next: InternalItem[]) => {
    setItems(next);
    const out = emit(next, props.asObject);
    lastEmittedRef.current = out;
    onChange(out);
  };

  const addRow = () => {
    updateItems([...items, { __id: generateId(), key: '', value: '', __justAdded: true }]);
  };

  const removeRow = (id: string) => {
    updateItems(items.filter((i) => i.__id !== id));
  };

  const updateRow = (id: string, patch: Partial<Pick<InternalItem, 'key' | 'value'>>) => {
    updateItems(items.map((i) => (i.__id === id ? { ...i, ...patch } : i)));
  };

  const legendId = `${baseId}-legend`;
  const describedByCombined = describedBy ?? undefined;

  return (
    <fieldset
      id={baseId}
      className="space-y-2"
      disabled={disabled}
      aria-describedby={describedByCombined}
      aria-invalid={hasError || undefined}
    >
      {field.label ? (
        <legend id={legendId} className="text-sm font-medium text-[var(--color-arqel-fg)]">
          {field.label}
        </legend>
      ) : null}

      <div className="grid grid-cols-[1fr_1fr_auto] gap-2">
        <div className="text-xs font-medium text-[var(--color-arqel-muted-fg)]">{props.keyLabel}</div>
        <div className="text-xs font-medium text-[var(--color-arqel-muted-fg)]">{props.valueLabel}</div>
        <div aria-hidden="true" />

        {items.map((item, index) => {
          const keyEditable = props.editableKeys || item.__justAdded;
          const rowKeyId = `${baseId}-row-${index}-key`;
          const rowValueId = `${baseId}-row-${index}-value`;
          return (
            <div key={item.__id} className="contents">
              <input
                id={rowKeyId}
                type="text"
                className={inputClasses}
                value={item.key}
                placeholder={props.keyPlaceholder || undefined}
                aria-label={`${props.keyLabel} ${index + 1}`}
                aria-invalid={hasError || undefined}
                readOnly={!keyEditable}
                disabled={!keyEditable && !item.__justAdded}
                onChange={(e) => updateRow(item.__id, { key: e.target.value })}
              />
              <input
                id={rowValueId}
                type="text"
                className={inputClasses}
                value={item.value}
                placeholder={props.valuePlaceholder || undefined}
                aria-label={`${props.valueLabel} ${index + 1}`}
                aria-invalid={hasError || undefined}
                onChange={(e) => updateRow(item.__id, { value: e.target.value })}
              />
              {props.deletable ? (
                <button
                  type="button"
                  className={removeButtonClasses}
                  aria-label={`Remove row ${index + 1}`}
                  onClick={() => removeRow(item.__id)}
                >
                  ×
                </button>
              ) : (
                <span aria-hidden="true" />
              )}
            </div>
          );
        })}
      </div>

      {props.addable ? (
        <button
          type="button"
          className={buttonClasses}
          onClick={addRow}
          aria-label={`Add ${props.keyLabel.toLowerCase()} / ${props.valueLabel.toLowerCase()} row`}
        >
          + Add row
        </button>
      ) : null}
    </fieldset>
  );
}

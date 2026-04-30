/**
 * `<RepeaterInput>` — list of nested mini-form items, one per row.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\RepeaterField`
 * (FIELDS-ADV-013, scoped). Reads the following props verbatim from
 * the PHP-emitted schema:
 *
 *   - `schema`       : array<{ name, type, label?, options?, ... }>
 *                      Nested sub-form schema. Each entry describes a
 *                      single leaf input rendered inline per item.
 *   - `minItems`     : ?number  — floor for the row count.
 *   - `maxItems`     : ?number  — ceiling for the row count.
 *   - `reorderable`  : boolean  — whether up/down move buttons show.
 *   - `collapsible`  : boolean  — whether the collapse toggle shows.
 *   - `cloneable`    : boolean  — whether the clone button shows.
 *   - `itemLabel`    : ?string  — `{{key}}`-templated header per row.
 *   - `relationship` : ?string  — read defensively but unused
 *                                 client-side (PHP-side hydration/
 *                                 persistence concern).
 *
 * ## Scope (FIELDS-ADV-013 — narrowed)
 *
 * The original spec used `@dnd-kit/sortable` for drag-drop reorder.
 * The dnd-kit stack adds ~30KB gz, requires KeyboardSensor + screen
 * reader announcer wiring for a11y, and is non-trivial to land in a
 * single drop-in commit. This component implements reorder via plain
 * "Move up" / "Move down" buttons instead, which already covers the
 * core a11y story (focusable, keyboard-operable). The dnd-kit
 * integration is deferred to a follow-up that adds the dep.
 */

import { useId, useState } from 'react';
import type { FieldRendererProps } from '../shared/types.js';

interface RepeaterProps {
  schema: SubFieldSchema[];
  minItems: number | null;
  maxItems: number | null;
  reorderable: boolean;
  collapsible: boolean;
  cloneable: boolean;
  itemLabel: string | null;
}

interface SubFieldSchema {
  name: string;
  type: string;
  label?: string;
  options?: ReadonlyArray<{ value: string | number; label: string }> | Record<string, string>;
  placeholder?: string;
}

interface InternalItem extends Record<string, unknown> {
  __id: string;
}

const SUPPORTED_TYPES = new Set([
  'text',
  'string',
  'number',
  'select',
  'textarea',
  'boolean',
  'checkbox',
]);

const inputClasses =
  'h-9 w-full rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] ' +
  'bg-[var(--color-arqel-bg)] px-3 text-sm text-[var(--color-arqel-fg)] ' +
  'placeholder:text-[var(--color-arqel-muted-fg)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'aria-invalid:border-[var(--color-arqel-destructive)]';

const textareaClasses =
  'w-full rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] ' +
  'bg-[var(--color-arqel-bg)] px-3 py-2 text-sm text-[var(--color-arqel-fg)] ' +
  'placeholder:text-[var(--color-arqel-muted-fg)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const buttonClasses =
  'inline-flex h-8 items-center justify-center rounded-[var(--radius-arqel-sm)] ' +
  'border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] ' +
  'px-3 text-sm text-[var(--color-arqel-fg)] ' +
  'hover:bg-[var(--color-arqel-muted)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const iconButtonClasses =
  'inline-flex h-8 w-8 items-center justify-center rounded-[var(--radius-arqel-sm)] ' +
  'border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] ' +
  'text-sm text-[var(--color-arqel-fg)] ' +
  'hover:bg-[var(--color-arqel-muted)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

function generateId(): string {
  if (
    typeof globalThis.crypto !== 'undefined' &&
    typeof globalThis.crypto.randomUUID === 'function'
  ) {
    return globalThis.crypto.randomUUID();
  }
  return `rep-${Math.random().toString(36).slice(2)}-${Date.now().toString(36)}`;
}

function readProps(raw: unknown): RepeaterProps {
  const p = (raw ?? {}) as Partial<Record<keyof RepeaterProps, unknown>>;
  const schema = Array.isArray(p.schema)
    ? (p.schema.filter(
        (entry): entry is SubFieldSchema =>
          typeof entry === 'object' &&
          entry !== null &&
          typeof (entry as { name?: unknown }).name === 'string' &&
          typeof (entry as { type?: unknown }).type === 'string',
      ) as SubFieldSchema[])
    : [];
  return {
    schema,
    minItems:
      typeof p.minItems === 'number' && Number.isFinite(p.minItems)
        ? Math.max(0, Math.floor(p.minItems))
        : null,
    maxItems:
      typeof p.maxItems === 'number' && Number.isFinite(p.maxItems)
        ? Math.max(1, Math.floor(p.maxItems))
        : null,
    reorderable: typeof p.reorderable === 'boolean' ? p.reorderable : true,
    collapsible: typeof p.collapsible === 'boolean' ? p.collapsible : false,
    cloneable: typeof p.cloneable === 'boolean' ? p.cloneable : true,
    itemLabel: typeof p.itemLabel === 'string' ? p.itemLabel : null,
  };
}

function defaultValueFor(type: string): unknown {
  switch (type) {
    case 'boolean':
    case 'checkbox':
      return false;
    case 'number':
      return null;
    default:
      return '';
  }
}

function emptyItem(schema: SubFieldSchema[]): InternalItem {
  const item: InternalItem = { __id: generateId() };
  for (const sub of schema) {
    item[sub.name] = defaultValueFor(sub.type);
  }
  return item;
}

function hydrate(value: unknown, schema: SubFieldSchema[]): InternalItem[] {
  if (!Array.isArray(value)) return [];
  return value
    .filter(
      (entry): entry is Record<string, unknown> => typeof entry === 'object' && entry !== null,
    )
    .map((entry) => {
      const out: InternalItem = { __id: generateId() };
      for (const sub of schema) {
        out[sub.name] =
          sub.name in entry
            ? (entry as Record<string, unknown>)[sub.name]
            : defaultValueFor(sub.type);
      }
      // Preserve unknown keys so schema/persisted-shape drift doesn't
      // silently drop user data.
      for (const [k, v] of Object.entries(entry)) {
        if (!(k in out)) out[k] = v;
      }
      return out;
    });
}

function emit(items: InternalItem[]): Array<Record<string, unknown>> {
  return items.map(({ __id: _id, ...rest }) => rest);
}

function resolveLabel(template: string | null, item: InternalItem, fallback: string): string {
  if (!template) return fallback;
  return template.replace(/\{\{\s*([\w.-]+)\s*\}\}/g, (_match, key: string) => {
    const v = item[key];
    if (v === undefined || v === null) return '';
    return String(v);
  });
}

interface SubFieldInputProps {
  field: SubFieldSchema;
  value: unknown;
  onChange: (next: unknown) => void;
  disabled?: boolean;
  inputId: string;
}

function SubFieldInput({ field, value, onChange, disabled, inputId }: SubFieldInputProps) {
  const type = field.type;

  if (type === 'textarea') {
    return (
      <textarea
        id={inputId}
        className={textareaClasses}
        value={typeof value === 'string' ? value : String(value ?? '')}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        rows={3}
        placeholder={field.placeholder}
      />
    );
  }

  if (type === 'boolean' || type === 'checkbox') {
    return (
      <input
        id={inputId}
        type="checkbox"
        className="h-4 w-4"
        checked={value === true}
        onChange={(e) => onChange(e.target.checked)}
        disabled={disabled}
      />
    );
  }

  if (type === 'number') {
    return (
      <input
        id={inputId}
        type="number"
        className={inputClasses}
        value={value === null || value === undefined ? '' : String(value)}
        onChange={(e) => {
          const raw = e.target.value;
          onChange(raw === '' ? null : Number(raw));
        }}
        disabled={disabled}
        placeholder={field.placeholder}
      />
    );
  }

  if (type === 'select') {
    const options = (() => {
      if (Array.isArray(field.options)) return field.options;
      if (field.options && typeof field.options === 'object') {
        return Object.entries(field.options as Record<string, string>).map(([v, label]) => ({
          value: v,
          label,
        }));
      }
      return [] as ReadonlyArray<{ value: string | number; label: string }>;
    })();
    return (
      <select
        id={inputId}
        className={inputClasses}
        value={value === null || value === undefined ? '' : String(value)}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
      >
        <option value="">—</option>
        {options.map((opt) => (
          <option key={String(opt.value)} value={String(opt.value)}>
            {opt.label}
          </option>
        ))}
      </select>
    );
  }

  // text / string / unknown fallback
  const note = SUPPORTED_TYPES.has(type) ? null : (
    <p className="mt-1 text-xs text-[var(--color-arqel-muted-fg)]">type {type} not yet supported</p>
  );

  return (
    <div>
      <input
        id={inputId}
        type="text"
        className={inputClasses}
        value={typeof value === 'string' ? value : String(value ?? '')}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        placeholder={field.placeholder}
      />
      {note}
    </div>
  );
}

export function RepeaterInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const props = readProps((field as { props?: unknown }).props);
  const hasError = errors !== undefined && errors.length > 0;
  const fallbackId = useId();
  const baseId = inputId ?? fallbackId;

  const [items, setItems] = useState<InternalItem[]>(() => hydrate(value, props.schema));
  const [collapsedIds, setCollapsedIds] = useState<Set<string>>(() => new Set());

  const updateItems = (next: InternalItem[]) => {
    setItems(next);
    onChange(emit(next));
  };

  const updateItemField = (id: string, name: string, next: unknown) => {
    updateItems(items.map((it) => (it.__id === id ? { ...it, [name]: next } : it)));
  };

  const moveItem = (index: number, delta: -1 | 1) => {
    const target = index + delta;
    if (target < 0 || target >= items.length) return;
    const next = items.slice();
    const a = next[index];
    const b = next[target];
    if (!a || !b) return;
    next[index] = b;
    next[target] = a;
    updateItems(next);
  };

  const removeItem = (id: string) => {
    if (props.minItems !== null && items.length <= props.minItems) return;
    updateItems(items.filter((it) => it.__id !== id));
  };

  const cloneItem = (id: string) => {
    if (props.maxItems !== null && items.length >= props.maxItems) return;
    const idx = items.findIndex((it) => it.__id === id);
    if (idx < 0) return;
    const source = items[idx];
    if (!source) return;
    const copy: InternalItem = { ...source, __id: generateId() };
    const next = items.slice();
    next.splice(idx + 1, 0, copy);
    updateItems(next);
  };

  const addItem = () => {
    if (props.maxItems !== null && items.length >= props.maxItems) return;
    updateItems([...items, emptyItem(props.schema)]);
  };

  const toggleCollapsed = (id: string) => {
    setCollapsedIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const atMax = props.maxItems !== null && items.length >= props.maxItems;
  const atMin = props.minItems !== null && items.length <= props.minItems;

  const legendId = `${baseId}-legend`;

  return (
    <fieldset
      id={baseId}
      className="space-y-3"
      disabled={disabled}
      aria-describedby={describedBy}
      aria-invalid={hasError || undefined}
    >
      {field.label ? (
        <legend id={legendId} className="text-sm font-medium text-[var(--color-arqel-fg)]">
          {field.label}
        </legend>
      ) : null}

      <ol className="list-none space-y-2 pl-0">
        {items.map((item, index) => {
          const itemTitleId = `${baseId}-item-${index}-title`;
          const fallbackLabel = `Item ${index + 1}`;
          const labelText = resolveLabel(props.itemLabel, item, fallbackLabel) || fallbackLabel;
          const isCollapsed = props.collapsible && collapsedIds.has(item.__id);
          return (
            <li key={item.__id}>
              <article
                aria-labelledby={itemTitleId}
                className="rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] p-3"
              >
                <header className="flex items-center justify-between gap-2">
                  <h3 id={itemTitleId} className="text-sm font-medium text-[var(--color-arqel-fg)]">
                    {labelText}
                  </h3>
                  <div className="flex items-center gap-1">
                    {props.collapsible ? (
                      <button
                        type="button"
                        className={iconButtonClasses}
                        aria-label={
                          isCollapsed ? `Expand item ${index + 1}` : `Collapse item ${index + 1}`
                        }
                        aria-expanded={!isCollapsed}
                        onClick={() => toggleCollapsed(item.__id)}
                      >
                        {isCollapsed ? '▸' : '▾'}
                      </button>
                    ) : null}
                    {props.reorderable ? (
                      <>
                        <button
                          type="button"
                          className={iconButtonClasses}
                          aria-label="Move up"
                          disabled={index === 0}
                          onClick={() => moveItem(index, -1)}
                        >
                          ↑
                        </button>
                        <button
                          type="button"
                          className={iconButtonClasses}
                          aria-label="Move down"
                          disabled={index === items.length - 1}
                          onClick={() => moveItem(index, 1)}
                        >
                          ↓
                        </button>
                      </>
                    ) : null}
                    {props.cloneable ? (
                      <button
                        type="button"
                        className={iconButtonClasses}
                        aria-label={`Clone item ${index + 1}`}
                        disabled={atMax}
                        onClick={() => cloneItem(item.__id)}
                      >
                        ⎘
                      </button>
                    ) : null}
                    <button
                      type="button"
                      className={iconButtonClasses}
                      aria-label={`Remove item ${index + 1}`}
                      disabled={atMin}
                      onClick={() => removeItem(item.__id)}
                    >
                      ×
                    </button>
                  </div>
                </header>

                {!isCollapsed ? (
                  <div className="mt-3 grid grid-cols-1 gap-3">
                    {props.schema.map((sub) => {
                      const subId = `${baseId}-item-${index}-${sub.name}`;
                      const subLabel = sub.label ?? sub.name;
                      return (
                        <div key={sub.name} className="grid gap-1">
                          <label
                            htmlFor={subId}
                            className="text-xs font-medium text-[var(--color-arqel-muted-fg)]"
                          >
                            {subLabel}
                          </label>
                          <SubFieldInput
                            field={sub}
                            value={item[sub.name]}
                            onChange={(next) => updateItemField(item.__id, sub.name, next)}
                            disabled={disabled}
                            inputId={subId}
                          />
                        </div>
                      );
                    })}
                  </div>
                ) : null}
              </article>
            </li>
          );
        })}
      </ol>

      <button
        type="button"
        className={buttonClasses}
        onClick={addItem}
        disabled={disabled || atMax}
        aria-label="Add item"
      >
        + Add item
      </button>
    </fieldset>
  );
}

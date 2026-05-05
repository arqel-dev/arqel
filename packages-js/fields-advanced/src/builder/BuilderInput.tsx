/**
 * `<BuilderInput>` — heterogeneous builder of typed blocks. Each item
 * picks one of a finite palette of block types, and renders that
 * block's own sub-form schema in line.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\BuilderField`
 * (FIELDS-ADV-014). Reads the following props verbatim from the
 * PHP-emitted schema (see `BuilderField::getTypeSpecificProps()`):
 *
 *   - `blocks`       : Record<string, { type, label, icon?, schema }>
 *                      Map of block-type → metadata + sub-form schema.
 *   - `minItems`     : ?number  — floor for the block count.
 *   - `maxItems`     : ?number  — ceiling for the block count.
 *   - `reorderable`  : boolean  — whether the drag handle and up/down
 *                                 move buttons show.
 *   - `collapsible`  : boolean  — whether the collapse toggle shows.
 *   - `cloneable`    : boolean  — whether the clone button shows.
 *   - `itemLabel`    : ?string  — `{{key}}`-templated header per block.
 *
 * ## Reordering (FIELDS-ADV-014 — full)
 *
 * Mirrors `<RepeaterInput>`: `@dnd-kit/sortable` drag-drop on a
 * dedicated handle (PointerSensor 5px activation + KeyboardSensor for
 * Space/Arrow/Space) plus the redundant "Move up" / "Move down" icon
 * buttons as a keyboard-only a11y fallback. The block picker still
 * uses a self-managed `role="menu"` dropdown (Base UI Dialog
 * migration is a separate follow-up).
 */

import {
  closestCenter,
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useEffect, useId, useRef, useState } from 'react';
import type { FieldRendererProps } from '../shared/types.js';

interface BlockSchemaEntry {
  type: string;
  label: string;
  icon: string | null;
  schema: SubFieldSchema[];
}

interface SubFieldSchema {
  name: string;
  type: string;
  label?: string;
  options?: ReadonlyArray<{ value: string | number; label: string }> | Record<string, string>;
  placeholder?: string;
}

interface BuilderProps {
  blocks: Record<string, BlockSchemaEntry>;
  minItems: number | null;
  maxItems: number | null;
  reorderable: boolean;
  collapsible: boolean;
  cloneable: boolean;
  itemLabel: string | null;
}

interface InternalItem {
  __id: string;
  type: string;
  data: Record<string, unknown>;
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
  'h-9 w-full rounded-sm border border-[var(--input)] ' +
  'bg-background px-3 text-sm text-foreground ' +
  'placeholder:text-muted-foreground ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'aria-invalid:border-destructive';

const textareaClasses =
  'w-full rounded-sm border border-[var(--input)] ' +
  'bg-background px-3 py-2 text-sm text-foreground ' +
  'placeholder:text-muted-foreground ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const buttonClasses =
  'inline-flex h-8 items-center justify-center rounded-sm ' +
  'border border-[var(--input)] bg-background ' +
  'px-3 text-sm text-foreground ' +
  'hover:bg-muted ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const iconButtonClasses =
  'inline-flex h-8 w-8 items-center justify-center rounded-sm ' +
  'border border-[var(--input)] bg-background ' +
  'text-sm text-foreground ' +
  'hover:bg-muted ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const dragHandleClasses =
  'inline-flex h-8 w-8 shrink-0 cursor-grab items-center justify-center rounded-sm ' +
  'border border-[var(--input)] bg-background ' +
  'text-sm text-muted-foreground ' +
  'hover:bg-muted ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'active:cursor-grabbing';

const menuItemClasses =
  'flex w-full items-center gap-2 rounded-sm px-3 py-2 text-left text-sm ' +
  'text-foreground hover:bg-muted ' +
  'focus-visible:bg-muted focus-visible:outline-none';

function generateId(): string {
  if (
    typeof globalThis.crypto !== 'undefined' &&
    typeof globalThis.crypto.randomUUID === 'function'
  ) {
    return globalThis.crypto.randomUUID();
  }
  return `bld-${Math.random().toString(36).slice(2)}-${Date.now().toString(36)}`;
}

function isSubFieldSchema(entry: unknown): entry is SubFieldSchema {
  return (
    typeof entry === 'object' &&
    entry !== null &&
    typeof (entry as { name?: unknown }).name === 'string' &&
    typeof (entry as { type?: unknown }).type === 'string'
  );
}

function readBlockEntry(raw: unknown, fallbackType: string): BlockSchemaEntry | null {
  if (typeof raw !== 'object' || raw === null) return null;
  const e = raw as Partial<Record<keyof BlockSchemaEntry, unknown>>;
  const type = typeof e.type === 'string' ? e.type : fallbackType;
  const label = typeof e.label === 'string' ? e.label : type;
  const icon = typeof e.icon === 'string' ? e.icon : null;
  const schema = Array.isArray(e.schema)
    ? (e.schema.filter(isSubFieldSchema) as SubFieldSchema[])
    : [];
  return { type, label, icon, schema };
}

function readProps(raw: unknown): BuilderProps {
  const p = (raw ?? {}) as Partial<Record<keyof BuilderProps, unknown>>;
  const blocks: Record<string, BlockSchemaEntry> = {};
  if (p.blocks && typeof p.blocks === 'object') {
    for (const [key, value] of Object.entries(p.blocks as Record<string, unknown>)) {
      const entry = readBlockEntry(value, key);
      if (entry) blocks[key] = entry;
    }
  }
  return {
    blocks,
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

function emptyData(schema: SubFieldSchema[]): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const sub of schema) {
    out[sub.name] = defaultValueFor(sub.type);
  }
  return out;
}

function hydrate(value: unknown, blocks: Record<string, BlockSchemaEntry>): InternalItem[] {
  if (!Array.isArray(value)) return [];
  return value
    .filter(
      (entry): entry is { type: unknown; data?: unknown } =>
        typeof entry === 'object' && entry !== null,
    )
    .filter(
      (entry): entry is { type: string; data?: Record<string, unknown> } =>
        typeof entry.type === 'string',
    )
    .map((entry) => {
      const block = blocks[entry.type];
      const baseData =
        entry.data && typeof entry.data === 'object' ? (entry.data as Record<string, unknown>) : {};
      const merged: Record<string, unknown> = block ? emptyData(block.schema) : {};
      for (const [k, v] of Object.entries(baseData)) {
        merged[k] = v;
      }
      return {
        __id: generateId(),
        type: entry.type,
        data: merged,
      } satisfies InternalItem;
    });
}

function emit(items: InternalItem[]): Array<{ type: string; data: Record<string, unknown> }> {
  return items.map(({ type, data }) => ({ type, data }));
}

function resolveLabel(
  template: string | null,
  data: Record<string, unknown>,
  fallback: string,
): string {
  if (!template) return fallback;
  return template.replace(/\{\{\s*([\w.-]+)\s*\}\}/g, (_match, key: string) => {
    const v = data[key];
    if (v === undefined || v === null) return '';
    return String(v);
  });
}

interface SubFieldInputProps {
  field: SubFieldSchema;
  value: unknown;
  onChange: (next: unknown) => void;
  disabled?: boolean | undefined;
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

  const note = SUPPORTED_TYPES.has(type) ? null : (
    <p className="mt-1 text-xs text-muted-foreground">type {type} not yet supported</p>
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

interface BlockPickerProps {
  blocks: BlockSchemaEntry[];
  onSelect: (type: string) => void;
  onClose: () => void;
}

function BlockPicker({ blocks, onSelect, onClose }: BlockPickerProps) {
  const menuRef = useRef<HTMLDivElement | null>(null);
  const [activeIndex, setActiveIndex] = useState(0);

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [onClose]);

  useEffect(() => {
    const node = menuRef.current;
    if (!node) return;
    const items = node.querySelectorAll<HTMLButtonElement>('[role="menuitem"]');
    const target = items[activeIndex];
    if (target) target.focus();
  }, [activeIndex]);

  const onKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActiveIndex((i) => (blocks.length === 0 ? 0 : (i + 1) % blocks.length));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActiveIndex((i) => (blocks.length === 0 ? 0 : (i - 1 + blocks.length) % blocks.length));
    }
  };

  return (
    <>
      <button
        type="button"
        data-testid="builder-picker-backdrop"
        className="fixed inset-0 z-40 cursor-default"
        onClick={onClose}
        tabIndex={-1}
        aria-label="Close block picker"
      />
      <div
        ref={menuRef}
        role="menu"
        aria-label="Add block"
        onKeyDown={onKeyDown}
        className="relative z-50 mt-1 w-64 rounded-sm border border-[var(--input)] bg-background p-1 shadow-md"
      >
        {blocks.map((block) => (
          <button
            key={block.type}
            type="button"
            role="menuitem"
            className={menuItemClasses}
            onClick={() => onSelect(block.type)}
          >
            {block.icon ? (
              <span aria-hidden="true" className="text-base">
                {block.icon}
              </span>
            ) : null}
            <span>{block.label}</span>
          </button>
        ))}
      </div>
    </>
  );
}

interface SortableRowProps {
  id: string;
  reorderable: boolean;
  index: number;
  children: (handleProps: {
    listeners: ReturnType<typeof useSortable>['listeners'];
    attributes: ReturnType<typeof useSortable>['attributes'];
  }) => React.ReactNode;
}

function SortableRow({ id, reorderable, index, children }: SortableRowProps) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id,
    disabled: !reorderable,
  });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.6 : 1,
    zIndex: isDragging ? 1 : undefined,
    position: 'relative',
  };

  return (
    <li ref={setNodeRef} style={style} data-block-index={index}>
      {children({ listeners, attributes })}
    </li>
  );
}

export function BuilderInput({
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

  const [items, setItems] = useState<InternalItem[]>(() => hydrate(value, props.blocks));
  const [collapsedIds, setCollapsedIds] = useState<Set<string>>(() => new Set());
  const [pickerOpen, setPickerOpen] = useState(false);

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const updateItems = (next: InternalItem[]) => {
    setItems(next);
    onChange(emit(next));
  };

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (!over || active.id === over.id) return;
    const oldIndex = items.findIndex((it) => it.__id === active.id);
    const newIndex = items.findIndex((it) => it.__id === over.id);
    if (oldIndex < 0 || newIndex < 0) return;
    updateItems(arrayMove(items, oldIndex, newIndex));
  };

  const updateItemField = (id: string, name: string, next: unknown) => {
    updateItems(
      items.map((it) => (it.__id === id ? { ...it, data: { ...it.data, [name]: next } } : it)),
    );
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
    const copy: InternalItem = {
      __id: generateId(),
      type: source.type,
      data: { ...source.data },
    };
    const next = items.slice();
    next.splice(idx + 1, 0, copy);
    updateItems(next);
  };

  const addBlock = (type: string) => {
    if (props.maxItems !== null && items.length >= props.maxItems) return;
    const block = props.blocks[type];
    const data = block ? emptyData(block.schema) : {};
    const next: InternalItem[] = [...items, { __id: generateId(), type, data }];
    updateItems(next);
    setPickerOpen(false);
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

  const blockList = Object.values(props.blocks);
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
        <legend id={legendId} className="text-sm font-medium text-foreground">
          {field.label}
        </legend>
      ) : null}

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={items.map((i) => i.__id)} strategy={verticalListSortingStrategy}>
          <ol className="list-none space-y-2 pl-0">
            {items.map((item, index) => {
              const itemTitleId = `${baseId}-item-${index}-title`;
              const block = props.blocks[item.type];
              const blockLabel = block?.label ?? item.type;
              const fallbackLabel = `Block ${index + 1} — ${blockLabel}`;
              const labelText =
                resolveLabel(props.itemLabel, item.data, fallbackLabel) || fallbackLabel;
              const isCollapsed = props.collapsible && collapsedIds.has(item.__id);
              const subSchema = block?.schema ?? [];
              return (
                <SortableRow
                  key={item.__id}
                  id={item.__id}
                  reorderable={props.reorderable}
                  index={index}
                >
                  {({ listeners, attributes }) => (
                    <article
                      aria-labelledby={itemTitleId}
                      data-block-type={item.type}
                      className="rounded-sm border border-[var(--input)] bg-background p-3"
                    >
                      <header className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2">
                          {props.reorderable ? (
                            <button
                              type="button"
                              className={dragHandleClasses}
                              aria-label={`Drag to reorder block ${index + 1}`}
                              data-testid={`builder-drag-handle-${index}`}
                              {...attributes}
                              {...listeners}
                            >
                              ≡
                            </button>
                          ) : null}
                          <h3 id={itemTitleId} className="text-sm font-medium text-foreground">
                            {labelText}
                          </h3>
                        </div>
                        <div className="flex items-center gap-1">
                          {props.collapsible ? (
                            <button
                              type="button"
                              className={iconButtonClasses}
                              aria-label={
                                isCollapsed
                                  ? `Expand block ${index + 1}`
                                  : `Collapse block ${index + 1}`
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
                                aria-label={`Move block ${index + 1} up`}
                                disabled={index === 0}
                                onClick={() => moveItem(index, -1)}
                              >
                                ↑
                              </button>
                              <button
                                type="button"
                                className={iconButtonClasses}
                                aria-label={`Move block ${index + 1} down`}
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
                              aria-label={`Clone block ${index + 1}`}
                              disabled={atMax}
                              onClick={() => cloneItem(item.__id)}
                            >
                              ⎘
                            </button>
                          ) : null}
                          <button
                            type="button"
                            className={iconButtonClasses}
                            aria-label={`Remove block ${index + 1}`}
                            disabled={atMin}
                            onClick={() => removeItem(item.__id)}
                          >
                            ×
                          </button>
                        </div>
                      </header>

                      {!isCollapsed ? (
                        <div className="mt-3 grid grid-cols-1 gap-3">
                          {subSchema.map((sub) => {
                            const subId = `${baseId}-item-${index}-${sub.name}`;
                            const subLabel = sub.label ?? sub.name;
                            return (
                              <div key={sub.name} className="grid gap-1">
                                <label
                                  htmlFor={subId}
                                  className="text-xs font-medium text-muted-foreground"
                                >
                                  {subLabel}
                                </label>
                                <SubFieldInput
                                  field={sub}
                                  value={item.data[sub.name]}
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
                  )}
                </SortableRow>
              );
            })}
          </ol>
        </SortableContext>
      </DndContext>

      <div className="relative inline-block">
        <button
          type="button"
          className={buttonClasses}
          onClick={() => setPickerOpen((open) => !open)}
          disabled={disabled || atMax}
          aria-haspopup="menu"
          aria-expanded={pickerOpen}
          aria-label="Add block"
        >
          + Add block
        </button>
        {pickerOpen ? (
          <BlockPicker
            blocks={blockList}
            onSelect={addBlock}
            onClose={() => setPickerOpen(false)}
          />
        ) : null}
      </div>
    </fieldset>
  );
}

/**
 * `<BelongsToInput>` — async-search picker for belongsTo relationships.
 *
 * Hits the search route emitted server-side (`field.props.searchRoute`,
 * registered by `arqel/fields` `FieldSearchController`) with a 300ms
 * debounce. Returned options are { value: id, label: title } pairs.
 *
 * Phase 1 ships a list-rendered picker (combobox with native `<input>`
 * + `<ul>`); Base UI Combobox lands in a follow-up.
 */

import type { BelongsToFieldSchema } from '@arqel/types/fields';
import type { FieldRendererProps } from '@arqel/ui/form';
import { cn } from '@arqel/ui/utils';
import { useEffect, useRef, useState } from 'react';
import { inputClasses } from '../shared/styles.js';

interface SearchResult {
  value: string | number;
  label: string;
}

export function BelongsToInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as BelongsToFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;

  const [query, setQuery] = useState('');
  const [results, setResults] = useState<SearchResult[]>([]);
  const [open, setOpen] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);

  useEffect(() => {
    if (!f.props.searchRoute || !query) {
      setResults([]);
      return;
    }
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;
      const url = `${f.props.searchRoute}?q=${encodeURIComponent(query)}`;
      fetch(url, { signal: controller.signal, headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : []))
        .then((data) => {
          if (Array.isArray(data)) setResults(data);
        })
        .catch(() => {
          /* aborts/network — silently swallow */
        });
    }, 300);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [query, f.props.searchRoute]);

  const selectedLabel = results.find((r) => String(r.value) === String(value))?.label;

  return (
    <div className="relative">
      <input
        id={inputId}
        type="text"
        className={inputClasses}
        value={
          open
            ? query
            : (selectedLabel ?? (value === null || value === undefined ? '' : String(value)))
        }
        placeholder={f.placeholder ?? `Search ${f.props.relatedResource}…`}
        disabled={isDisabled}
        readOnly={f.readonly === true || f.readonly === undefined ? false : true}
        aria-invalid={hasError || undefined}
        aria-describedby={describedBy}
        aria-expanded={open}
        aria-controls={`${inputId}-listbox`}
        role="combobox"
        onFocus={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 100)}
        onChange={(e) => setQuery(e.target.value)}
      />
      {open && results.length > 0 && (
        <div
          id={`${inputId}-listbox`}
          role="listbox"
          className={cn(
            'absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-border)] bg-[var(--color-arqel-bg)] shadow-md',
          )}
        >
          {results.map((r) => (
            <div
              key={String(r.value)}
              role="option"
              aria-selected={String(r.value) === String(value)}
              tabIndex={-1}
              className="cursor-pointer px-3 py-1.5 text-sm hover:bg-[var(--color-arqel-muted)]"
              onMouseDown={(e) => {
                e.preventDefault();
                onChange(r.value);
                setQuery('');
                setOpen(false);
              }}
            >
              {r.label}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

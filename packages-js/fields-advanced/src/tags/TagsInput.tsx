/**
 * `<TagsInput>` — chip-based tag editor for the PHP `TagsField`.
 *
 * Mirrors the props emitted by `Arqel\FieldsAdvanced\Types\TagsField`:
 *   - `suggestions`  : string[]   (resolved on the PHP side)
 *   - `creatable`    : boolean    (can user invent tags off-list?)
 *   - `maxTags`      : number|null
 *   - `separator`    : string     (single char committing the tag)
 *   - `unique`       : boolean    (case-insensitive de-dup)
 *
 * Implemented with vanilla React only — no Headless UI / Combobox lib.
 */

import type { FieldRendererProps } from '@arqel-dev/ui/form';
import {
  type KeyboardEvent as ReactKeyboardEvent,
  useEffect,
  useId,
  useMemo,
  useState,
} from 'react';

interface TagsFieldProps {
  suggestions: string[];
  creatable: boolean;
  maxTags: number | null;
  separator: string;
  unique: boolean;
}

function readTagsProps(raw: unknown): TagsFieldProps {
  const p = (raw ?? {}) as Partial<Record<keyof TagsFieldProps, unknown>>;
  const suggestions = Array.isArray(p.suggestions)
    ? p.suggestions.filter((s): s is string => typeof s === 'string')
    : [];
  return {
    suggestions,
    creatable: typeof p.creatable === 'boolean' ? p.creatable : true,
    maxTags: typeof p.maxTags === 'number' && Number.isFinite(p.maxTags) ? p.maxTags : null,
    separator: typeof p.separator === 'string' && p.separator.length > 0 ? p.separator : ',',
    unique: typeof p.unique === 'boolean' ? p.unique : true,
  };
}

function hydrateValue(value: unknown): string[] {
  if (!Array.isArray(value)) return [];
  return value.filter((v): v is string => typeof v === 'string');
}

export function TagsInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const props = readTagsProps((field as { props: unknown }).props);
  const hasError = errors !== undefined && errors.length > 0;

  const [tags, setTags] = useState<string[]>(() => hydrateValue(value));
  const [draft, setDraft] = useState('');
  const [open, setOpen] = useState(false);
  const [highlighted, setHighlighted] = useState(0);
  const reactListId = useId();
  const listId = `${reactListId}-tags-listbox`;

  // Hydrate from outer `value` when it changes externally.
  useEffect(() => {
    setTags(hydrateValue(value));
  }, [value]);

  const filteredSuggestions = useMemo(() => {
    if (draft.length === 0) return [];
    const needle = draft.toLowerCase();
    const taken = new Set(tags.map((t) => t.toLowerCase()));
    return props.suggestions.filter((s) => {
      if (props.unique && taken.has(s.toLowerCase())) return false;
      return s.toLowerCase().includes(needle);
    });
  }, [draft, props.suggestions, props.unique, tags]);

  function emit(next: string[]) {
    setTags(next);
    onChange(next);
  }

  function tryCommit(raw: string) {
    const trimmed = raw.trim();
    if (trimmed === '') return;
    if (props.maxTags !== null && tags.length >= props.maxTags) return;
    if (props.unique) {
      const lower = trimmed.toLowerCase();
      if (tags.some((t) => t.toLowerCase() === lower)) {
        setDraft('');
        return;
      }
    }
    if (!props.creatable) {
      const allowed = props.suggestions.some((s) => s.toLowerCase() === trimmed.toLowerCase());
      if (!allowed) {
        return;
      }
    }
    emit([...tags, trimmed]);
    setDraft('');
    setHighlighted(0);
  }

  function removeAt(index: number) {
    if (disabled) return;
    emit(tags.filter((_, i) => i !== index));
  }

  function handleKeyDown(event: ReactKeyboardEvent<HTMLInputElement>) {
    if (event.key === 'Enter') {
      event.preventDefault();
      if (open && filteredSuggestions.length > 0) {
        const pick = filteredSuggestions[highlighted];
        if (typeof pick === 'string') {
          tryCommit(pick);
          return;
        }
      }
      tryCommit(draft);
      return;
    }
    if (event.key === props.separator && props.separator.length === 1) {
      event.preventDefault();
      tryCommit(draft);
      return;
    }
    if (event.key === 'Backspace' && draft === '' && tags.length > 0) {
      event.preventDefault();
      emit(tags.slice(0, -1));
      return;
    }
    if (event.key === 'Escape') {
      setOpen(false);
      return;
    }
    if (event.key === 'ArrowDown' && open && filteredSuggestions.length > 0) {
      event.preventDefault();
      setHighlighted((h) => (h + 1) % filteredSuggestions.length);
      return;
    }
    if (event.key === 'ArrowUp' && open && filteredSuggestions.length > 0) {
      event.preventDefault();
      setHighlighted((h) => (h - 1 + filteredSuggestions.length) % filteredSuggestions.length);
      return;
    }
  }

  const showDropdown = open && draft.length > 0 && filteredSuggestions.length > 0;
  const ariaLabel = field.label ?? field.name;

  return (
    // biome-ignore lint/a11y/useFocusableInteractive: ARIA 1.2 combobox pattern — focus lives on the inner <input>; the wrapper just carries the role.
    <div
      role="combobox"
      aria-expanded={showDropdown}
      aria-haspopup="listbox"
      aria-controls={listId}
      className="relative"
    >
      <div className="flex flex-wrap items-center gap-2 rounded border border-[var(--input)] bg-background p-2">
        {tags.map((tag, index) => (
          <span
            // biome-ignore lint/suspicious/noArrayIndexKey: tags can repeat when unique=false; index disambiguates duplicates.
            key={`${tag}-${index}`}
            className="inline-flex items-center gap-1 rounded-full bg-secondary px-2 py-1 text-xs"
          >
            {tag}
            {!disabled && (
              <button
                type="button"
                aria-label={`Remove tag ${tag}`}
                onClick={() => removeAt(index)}
                className="ml-0.5 inline-flex h-4 w-4 items-center justify-center rounded-full hover:bg-black/10"
              >
                <span aria-hidden="true">×</span>
              </button>
            )}
          </span>
        ))}
        <input
          id={inputId}
          type="text"
          aria-label={ariaLabel}
          aria-autocomplete="list"
          aria-controls={listId}
          aria-activedescendant={showDropdown ? `${listId}-option-${highlighted}` : undefined}
          aria-invalid={hasError || undefined}
          aria-describedby={describedBy}
          disabled={disabled}
          value={draft}
          placeholder={field.placeholder ?? undefined}
          onChange={(event) => {
            setDraft(event.target.value);
            setOpen(true);
            setHighlighted(0);
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => {
            // Defer so a click on a suggestion can register first.
            window.setTimeout(() => setOpen(false), 100);
          }}
          onKeyDown={handleKeyDown}
          className="min-w-[8ch] flex-1 border-0 bg-transparent text-sm outline-none"
        />
      </div>

      {showDropdown && (
        <ul
          id={listId}
          // biome-ignore lint/a11y/noNoninteractiveElementToInteractiveRole: ARIA listbox is the canonical role for <ul> in a combobox pattern.
          role="listbox"
          className="absolute left-0 right-0 top-full z-10 mt-1 max-h-48 overflow-auto rounded border border-[var(--input)] bg-background py-1 text-sm shadow"
        >
          {filteredSuggestions.map((suggestion, index) => {
            const selected = index === highlighted;
            return (
              // biome-ignore lint/a11y/useKeyWithClickEvents: keyboard handling lives on the parent input (Enter/Arrow keys).
              // biome-ignore lint/a11y/useFocusableInteractive: keyboard navigation is handled via the input's aria-activedescendant.
              <li
                key={suggestion}
                id={`${listId}-option-${index}`}
                // biome-ignore lint/a11y/noNoninteractiveElementToInteractiveRole: ARIA option is the canonical role for <li> inside listbox.
                role="option"
                aria-selected={selected}
                className={
                  selected
                    ? 'cursor-pointer bg-[var(--accent)] px-3 py-1'
                    : 'cursor-pointer px-3 py-1 hover:bg-[var(--accent)]'
                }
                onMouseDown={(event) => {
                  // Prevent input blur from racing the click.
                  event.preventDefault();
                }}
                onClick={() => tryCommit(suggestion)}
              >
                {suggestion}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}

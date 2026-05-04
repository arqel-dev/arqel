/**
 * `<CommandPalette>` — global Cmd+K admin command palette.
 *
 * Wraps the `/admin/commands` endpoint shipped by `arqel-dev/core` (CMDPAL-002)
 * with a native `<dialog>`-based modal. No external dialog/combobox library
 * is used: the surface area is small enough that the platform primitives
 * (HTML dialog + ARIA combobox/listbox roles) cover every requirement.
 *
 * Usage: render once at the top of `<AppShell>` — the global Cmd+K /
 * Ctrl+K listener is mounted on `window` while the component is alive.
 */

import { type CSSProperties, useCallback, useEffect, useId, useRef, useState } from 'react';

export interface PaletteCommand {
  id: string;
  label: string;
  url: string;
  description?: string | null;
  category?: string | null;
  icon?: string | null;
}

export interface CommandPaletteProps {
  /** Endpoint that returns `{ commands: PaletteCommand[] }`. */
  endpoint?: string;
}

interface RecentEntry {
  id: string;
  count: number;
  lastUsed: number;
  command: PaletteCommand;
}

const RECENT_KEY = 'arqel:cmdpal:recent';
const RECENT_WINDOW_MS = 30 * 24 * 60 * 60 * 1000; // 30 days
const RECENT_LIMIT = 5;
const DEBOUNCE_MS = 150;

function readRecents(): RecentEntry[] {
  try {
    const raw = globalThis.localStorage?.getItem(RECENT_KEY);
    if (!raw) return [];
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];
    const now = Date.now();
    return parsed.filter(
      (e): e is RecentEntry =>
        typeof e === 'object' &&
        e !== null &&
        typeof (e as RecentEntry).id === 'string' &&
        typeof (e as RecentEntry).count === 'number' &&
        typeof (e as RecentEntry).lastUsed === 'number' &&
        now - (e as RecentEntry).lastUsed <= RECENT_WINDOW_MS &&
        typeof (e as RecentEntry).command === 'object',
    );
  } catch {
    return [];
  }
}

function writeRecents(entries: RecentEntry[]): void {
  try {
    globalThis.localStorage?.setItem(RECENT_KEY, JSON.stringify(entries));
  } catch {
    // Ignore quota/serialization failures — recents are best-effort.
  }
}

function recordUsage(command: PaletteCommand): void {
  const entries = readRecents();
  const now = Date.now();
  const idx = entries.findIndex((e) => e.id === command.id);
  if (idx >= 0) {
    const existing = entries[idx];
    if (existing) {
      entries[idx] = {
        ...existing,
        count: existing.count + 1,
        lastUsed: now,
        command,
      };
    }
  } else {
    entries.push({ id: command.id, count: 1, lastUsed: now, command });
  }
  // Prune anything older than the recency window.
  const fresh = entries.filter((e) => now - e.lastUsed <= RECENT_WINDOW_MS);
  writeRecents(fresh);
}

function topRecents(): PaletteCommand[] {
  return readRecents()
    .slice()
    .sort((a, b) => (b.count === a.count ? b.lastUsed - a.lastUsed : b.count - a.count))
    .slice(0, RECENT_LIMIT)
    .map((e) => e.command);
}

interface Group {
  category: string;
  commands: PaletteCommand[];
}

function groupByCategory(commands: PaletteCommand[]): Group[] {
  const order: string[] = [];
  const buckets = new Map<string, PaletteCommand[]>();
  for (const cmd of commands) {
    const key = cmd.category ?? 'General';
    if (!buckets.has(key)) {
      buckets.set(key, []);
      order.push(key);
    }
    buckets.get(key)?.push(cmd);
  }
  return order.map((category) => ({ category, commands: buckets.get(category) ?? [] }));
}

function flattenGroups(groups: Group[]): PaletteCommand[] {
  return groups.flatMap((g) => g.commands);
}

const dialogStyle: CSSProperties = {
  border: 'none',
  borderRadius: '0.75rem',
  padding: 0,
  width: 'min(640px, 92vw)',
  maxHeight: '70vh',
  background: 'var(--color-arqel-bg, #fff)',
  color: 'var(--color-arqel-fg, #111)',
};

export function CommandPalette({ endpoint = '/admin/commands' }: CommandPaletteProps) {
  const dialogRef = useRef<HTMLDialogElement | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);
  const debounceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<PaletteCommand[]>([]);
  const [recents, setRecents] = useState<PaletteCommand[]>([]);
  const [highlight, setHighlight] = useState(0);

  const titleId = useId();
  const hintId = useId();
  const listId = useId();
  const optBaseId = useId();

  const visibleCommands = query.trim().length === 0 ? recents : results;
  const groups =
    query.trim().length === 0 && recents.length > 0
      ? [{ category: 'Recent', commands: recents }]
      : groupByCategory(visibleCommands);
  const flat = flattenGroups(groups);

  // Cmd+K / Ctrl+K global toggle.
  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      const isToggle = (event.key === 'k' || event.key === 'K') && (event.metaKey || event.ctrlKey);
      if (!isToggle) return;
      event.preventDefault();
      setOpen((prev) => !prev);
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  // Sync `open` state with the native <dialog>.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    if (open && !dialog.open) {
      try {
        dialog.showModal();
      } catch {
        // jsdom or already-open: ignore.
      }
      setRecents(topRecents());
      setQuery('');
      setResults([]);
      setHighlight(0);
      // Defer focus after the dialog is on-screen.
      queueMicrotask(() => inputRef.current?.focus());
    } else if (!open && dialog.open) {
      dialog.close();
    }
  }, [open]);

  // Debounced fetch.
  useEffect(() => {
    if (!open) return;
    if (query.trim().length === 0) {
      setResults([]);
      return;
    }
    if (debounceTimer.current) clearTimeout(debounceTimer.current);
    debounceTimer.current = setTimeout(() => {
      const url = `${endpoint}?q=${encodeURIComponent(query)}`;
      fetch(url, { headers: { Accept: 'application/json' } })
        .then((res) => (res.ok ? res.json() : { commands: [] }))
        .then((data: { commands?: PaletteCommand[] }) => {
          setResults(Array.isArray(data?.commands) ? data.commands : []);
          setHighlight(0);
        })
        .catch(() => {
          setResults([]);
        });
    }, DEBOUNCE_MS);
    return () => {
      if (debounceTimer.current) clearTimeout(debounceTimer.current);
    };
  }, [query, open, endpoint]);

  const close = useCallback(() => setOpen(false), []);

  const execute = useCallback((command: PaletteCommand) => {
    recordUsage(command);
    setOpen(false);
    window.location.assign(command.url);
  }, []);

  const onKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLDivElement>) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
      }
      if (flat.length === 0) return;
      if (event.key === 'ArrowDown') {
        event.preventDefault();
        setHighlight((h) => (h + 1) % flat.length);
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        setHighlight((h) => (h - 1 + flat.length) % flat.length);
      } else if (event.key === 'Enter') {
        event.preventDefault();
        const target = flat[highlight];
        if (target) execute(target);
      }
    },
    [flat, highlight, close, execute],
  );

  // Close on backdrop click — dialog click hits the dialog itself, not children.
  const onDialogClick = useCallback(
    (event: React.MouseEvent<HTMLDialogElement>) => {
      if (event.target === dialogRef.current) close();
    },
    [close],
  );

  const optionId = (idx: number) => `${optBaseId}-opt-${idx}`;
  const activeOptionId = flat.length > 0 ? optionId(highlight) : undefined;

  return (
    // biome-ignore lint/a11y/useKeyWithClickEvents: dialog backdrop click closes; Escape (onCancel) handles keyboard close
    <dialog
      ref={dialogRef}
      aria-labelledby={titleId}
      aria-describedby={hintId}
      onClick={onDialogClick}
      onClose={() => setOpen(false)}
      onCancel={(event) => {
        event.preventDefault();
        close();
      }}
      style={dialogStyle}
      className="backdrop:bg-black/40"
      data-arqel-cmdpal=""
    >
      {/* biome-ignore lint/a11y/noStaticElementInteractions: this wrapper only delegates ArrowUp/Down/Enter/Escape from the combobox input below */}
      <div onKeyDown={onKeyDown} className="flex flex-col">
        <h2 id={titleId} className="sr-only">
          Command palette
        </h2>
        <input
          ref={inputRef}
          type="text"
          role="combobox"
          aria-expanded={open}
          aria-controls={listId}
          aria-autocomplete="list"
          {...(activeOptionId ? { 'aria-activedescendant': activeOptionId } : {})}
          placeholder="Type a command…"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          className="w-full border-b border-[var(--color-arqel-border,#e5e7eb)] bg-transparent px-4 py-3 text-base outline-none"
        />
        <div role="status" aria-live="polite" className="sr-only">
          {flat.length} commands
        </div>
        <ul
          id={listId}
          // biome-ignore lint/a11y/noNoninteractiveElementToInteractiveRole: WAI-ARIA combobox/listbox pattern requires <ul role="listbox">
          role="listbox"
          aria-label="Commands"
          className="max-h-[50vh] overflow-y-auto py-2"
        >
          {groups.length === 0 && (
            <li role="presentation" className="px-4 py-3 text-sm opacity-60">
              No commands found
            </li>
          )}
          {groups.map((group) => {
            const startIdx = flat.indexOf(group.commands[0] ?? ({} as PaletteCommand));
            return (
              <li key={group.category} role="presentation">
                <div
                  role="presentation"
                  className="px-4 pt-2 pb-1 text-xs font-semibold uppercase tracking-wide opacity-60"
                >
                  {group.category}
                </div>
                <ul role="presentation" className="m-0 list-none p-0">
                  {group.commands.map((cmd, j) => {
                    const idx = startIdx + j;
                    const isActive = idx === highlight;
                    return (
                      // biome-ignore lint/a11y/useFocusableInteractive: focus stays on combobox; selection via aria-activedescendant
                      // biome-ignore lint/a11y/useKeyWithClickEvents: keyboard nav handled by combobox input ArrowUp/Down/Enter handler
                      <li
                        key={cmd.id}
                        id={optionId(idx)}
                        // biome-ignore lint/a11y/noNoninteractiveElementToInteractiveRole: WAI-ARIA combobox/listbox pattern requires <li role="option">
                        role="option"
                        aria-selected={isActive}
                        data-active={isActive ? '' : undefined}
                        onMouseEnter={() => setHighlight(idx)}
                        onClick={() => execute(cmd)}
                        className={
                          'flex cursor-pointer items-center gap-3 px-4 py-2 text-sm ' +
                          (isActive ? 'bg-[var(--color-arqel-accent,#f1f5f9)]' : '')
                        }
                      >
                        <span className="flex-1">{cmd.label}</span>
                        {cmd.description ? (
                          <span className="text-xs opacity-60">{cmd.description}</span>
                        ) : null}
                      </li>
                    );
                  })}
                </ul>
              </li>
            );
          })}
        </ul>
        <footer
          id={hintId}
          className="flex items-center gap-3 border-t border-[var(--color-arqel-border,#e5e7eb)] px-4 py-2 text-xs opacity-70"
        >
          <span>↑↓ navigate</span>
          <span>↵ select</span>
          <span>esc close</span>
        </footer>
      </div>
    </dialog>
  );
}

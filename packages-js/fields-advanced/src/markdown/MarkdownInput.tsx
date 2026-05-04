/**
 * `<MarkdownInput>` — textarea-based markdown editor with live preview.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\MarkdownField`
 * (FIELDS-ADV-011, scoped). Reads the following props verbatim from the
 * PHP-emitted schema:
 *
 *   - `preview`     : boolean  — whether the preview pane is shown.
 *   - `previewMode` : 'side-by-side' | 'tab' | 'popup'
 *   - `toolbar`     : boolean  — whether the formatting toolbar shows.
 *   - `rows`        : number   — textarea row count (clamped >= 3).
 *   - `fullscreen`  : boolean  — whether the fullscreen toggle shows.
 *   - `syncScroll`  : boolean  — read defensively but unused in this
 *                                scope; deferred to the CodeMirror-based
 *                                follow-up.
 *
 * ## Scope (FIELDS-ADV-011 — narrowed)
 *
 * The original ticket described a CodeMirror 6 + remark/rehype-sanitize
 * stack. Both bring sizeable runtime dependencies (~80KB gz combined)
 * and won't fit a single drop-in commit. This component intentionally
 * implements a minimal, dependency-free markdown to HTML converter that
 * covers headings, bold, italic, inline code, fenced code blocks, links
 * (with a `javascript:` URL guard), and ordered/unordered lists. Full
 * GFM (tables, task lists, strikethrough, footnotes, autolinks, etc.)
 * is NOT supported. Consumers who need a richer preview should
 * register a custom component for the `MarkdownInput` slot via the
 * `@arqel-dev/ui` `FieldRegistry` and opt into a heavier renderer there.
 */

import type { FieldRendererProps } from '@arqel-dev/ui/form';
import {
  type ChangeEvent,
  type CSSProperties,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
} from 'react';

interface MarkdownProps {
  preview: boolean;
  previewMode: 'side-by-side' | 'tab' | 'popup';
  toolbar: boolean;
  rows: number;
  fullscreen: boolean;
  syncScroll: boolean;
}

const MIN_ROWS = 3;
const DEFAULT_ROWS = 10;
const PREVIEW_MODES: ReadonlyArray<MarkdownProps['previewMode']> = ['side-by-side', 'tab', 'popup'];

function readProps(raw: unknown): MarkdownProps {
  const p = (raw ?? {}) as Partial<Record<keyof MarkdownProps, unknown>>;
  const previewMode =
    typeof p.previewMode === 'string' &&
    (PREVIEW_MODES as ReadonlyArray<string>).includes(p.previewMode)
      ? (p.previewMode as MarkdownProps['previewMode'])
      : 'side-by-side';
  const rawRows = typeof p.rows === 'number' && Number.isFinite(p.rows) ? p.rows : DEFAULT_ROWS;
  return {
    preview: typeof p.preview === 'boolean' ? p.preview : true,
    previewMode,
    toolbar: typeof p.toolbar === 'boolean' ? p.toolbar : true,
    rows: Math.max(MIN_ROWS, Math.floor(rawRows)),
    fullscreen: typeof p.fullscreen === 'boolean' ? p.fullscreen : true,
    syncScroll: typeof p.syncScroll === 'boolean' ? p.syncScroll : true,
  };
}

/* -------------------------------------------------------------------------- */
/* Tiny markdown to HTML converter                                             */
/* -------------------------------------------------------------------------- */

function escapeHtml(input: string): string {
  return input
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** Reject anything other than http(s)/mailto/relative paths. */
function safeHref(raw: string): string | null {
  const trimmed = raw.trim();
  if (trimmed.length === 0) return null;
  const lower = trimmed.toLowerCase();
  if (
    lower.startsWith('javascript:') ||
    lower.startsWith('data:') ||
    lower.startsWith('vbscript:')
  ) {
    return null;
  }
  return trimmed;
}

function applyInline(escaped: string): string {
  // Inline code first — placeholder out so further substitutions don't
  // touch its body.
  const codes: string[] = [];
  let out = escaped.replace(/`([^`\n]+)`/g, (_match, inner: string) => {
    const idx = codes.push(`<code>${inner}</code>`) - 1;
    return ` CODE${idx} `;
  });

  // Links [text](url). The URL was HTML-escaped already, so an
  // attacker-supplied `javascript:` is still recognisable.
  out = out.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_m, text: string, rawUrl: string) => {
    const probe = rawUrl.replace(/&amp;/g, '&');
    const safe = safeHref(probe);
    if (safe === null) {
      return `[${text}](${rawUrl})`;
    }
    return `<a href="${rawUrl}">${text}</a>`;
  });

  // Bold **x** runs before italic _x_/*x* so `**` doesn't trigger
  // single-`*` italic logic.
  out = out.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
  out = out.replace(/(^|[^*\w])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
  out = out.replace(/(^|[^_\w])_([^_\n]+)_(?!_)/g, '$1<em>$2</em>');

  // Restore inline-code placeholders.
  out = out.replace(/ CODE(\d+) /g, (_m, idx: string) => codes[Number(idx)] ?? '');
  return out;
}

/**
 * Minimal markdown to HTML converter. Input is HTML-escaped first so
 * user content cannot break out. Exported for tests; not part of the
 * package's public API surface.
 */
export function markdownToHtml(source: string): string {
  if (typeof source !== 'string' || source.length === 0) return '';

  const lines = source.split(/\r?\n/);
  const out: string[] = [];

  let i = 0;
  while (i < lines.length) {
    const line = lines[i] ?? '';

    // Fenced code block ```lang ... ```
    if (/^```/.test(line)) {
      const code: string[] = [];
      i += 1;
      while (i < lines.length && !/^```/.test(lines[i] ?? '')) {
        code.push(lines[i] ?? '');
        i += 1;
      }
      i += 1; // skip closing ```
      out.push(`<pre><code>${escapeHtml(code.join('\n'))}</code></pre>`);
      continue;
    }

    const heading = /^(#{1,3})\s+(.*)$/.exec(line);
    if (heading) {
      const level = heading[1]?.length ?? 1;
      const text = applyInline(escapeHtml(heading[2] ?? ''));
      out.push(`<h${level}>${text}</h${level}>`);
      i += 1;
      continue;
    }

    if (/^\s*-\s+/.test(line)) {
      const items: string[] = [];
      while (i < lines.length && /^\s*-\s+/.test(lines[i] ?? '')) {
        const item = (lines[i] ?? '').replace(/^\s*-\s+/, '');
        items.push(`<li>${applyInline(escapeHtml(item))}</li>`);
        i += 1;
      }
      out.push(`<ul>${items.join('')}</ul>`);
      continue;
    }

    if (/^\s*\d+\.\s+/.test(line)) {
      const items: string[] = [];
      while (i < lines.length && /^\s*\d+\.\s+/.test(lines[i] ?? '')) {
        const item = (lines[i] ?? '').replace(/^\s*\d+\.\s+/, '');
        items.push(`<li>${applyInline(escapeHtml(item))}</li>`);
        i += 1;
      }
      out.push(`<ol>${items.join('')}</ol>`);
      continue;
    }

    if (line.trim().length === 0) {
      i += 1;
      continue;
    }

    const paragraph: string[] = [];
    while (i < lines.length) {
      const l = lines[i] ?? '';
      if (
        l.trim().length === 0 ||
        /^```/.test(l) ||
        /^(#{1,3})\s+/.test(l) ||
        /^\s*-\s+/.test(l) ||
        /^\s*\d+\.\s+/.test(l)
      ) {
        break;
      }
      paragraph.push(l);
      i += 1;
    }
    const joined = paragraph.join(' ');
    out.push(`<p>${applyInline(escapeHtml(joined))}</p>`);
  }

  return out.join('');
}

/* -------------------------------------------------------------------------- */
/* Toolbar action helpers                                                      */
/* -------------------------------------------------------------------------- */

type ToolbarAction = 'bold' | 'italic' | 'heading' | 'code' | 'link' | 'list';

interface SelectionEdit {
  next: string;
  selectionStart: number;
  selectionEnd: number;
}

function wrapSelection(
  source: string,
  start: number,
  end: number,
  before: string,
  after: string,
  placeholder: string,
): SelectionEdit {
  const selected = source.slice(start, end);
  const insert = selected.length > 0 ? selected : placeholder;
  const next = `${source.slice(0, start)}${before}${insert}${after}${source.slice(end)}`;
  return {
    next,
    selectionStart: start + before.length,
    selectionEnd: start + before.length + insert.length,
  };
}

function prefixLine(source: string, start: number, end: number, prefix: string): SelectionEdit {
  const lineStart = source.lastIndexOf('\n', Math.max(0, start - 1)) + 1;
  const next = `${source.slice(0, lineStart)}${prefix}${source.slice(lineStart)}`;
  return {
    next,
    selectionStart: start + prefix.length,
    selectionEnd: end + prefix.length,
  };
}

/* -------------------------------------------------------------------------- */
/* Component                                                                   */
/* -------------------------------------------------------------------------- */

const textareaClasses =
  'w-full rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] ' +
  'bg-[var(--color-arqel-bg)] px-3 py-2 font-mono text-sm text-[var(--color-arqel-fg)] ' +
  'placeholder:text-[var(--color-arqel-muted-fg)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'aria-invalid:border-[var(--color-arqel-destructive)]';

const buttonClasses =
  'inline-flex h-8 items-center justify-center rounded-[var(--radius-arqel-sm)] ' +
  'border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] ' +
  'px-2 text-xs text-[var(--color-arqel-fg)] ' +
  'hover:bg-[var(--color-arqel-muted)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const tabClasses =
  'inline-flex h-8 items-center justify-center rounded-[var(--radius-arqel-sm)] ' +
  'px-3 text-xs text-[var(--color-arqel-fg)] ' +
  'aria-selected:bg-[var(--color-arqel-muted)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]';

const previewPaneClasses =
  'prose prose-sm max-w-none overflow-auto rounded-[var(--radius-arqel-sm)] ' +
  'border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] p-3 ' +
  'text-sm text-[var(--color-arqel-fg)]';

const fullscreenStyle: CSSProperties = {
  position: 'fixed',
  inset: 0,
  zIndex: 50,
  background: 'var(--color-arqel-bg)',
  padding: '1rem',
  overflow: 'auto',
};

export function MarkdownInput({
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
  const id = inputId ?? fallbackId;

  const text = typeof value === 'string' ? value : '';

  const textareaRef = useRef<HTMLTextAreaElement | null>(null);
  const dialogRef = useRef<HTMLDialogElement | null>(null);
  const [activeTab, setActiveTab] = useState<'edit' | 'preview'>('edit');
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [isPopupOpen, setIsPopupOpen] = useState(false);

  const previewHtml = useMemo(() => markdownToHtml(text), [text]);

  // Keep the native <dialog> in sync — `showModal()` / `close()` are
  // imperative APIs.
  useEffect(() => {
    const dlg = dialogRef.current;
    if (!dlg) return;
    if (isPopupOpen && !dlg.open) {
      try {
        dlg.showModal();
      } catch {
        // jsdom and some older browsers don't implement showModal —
        // fall back to setting `open` directly.
        dlg.setAttribute('open', '');
      }
    } else if (!isPopupOpen && dlg.open) {
      dlg.close();
    }
  }, [isPopupOpen]);

  const handleTextChange = (event: ChangeEvent<HTMLTextAreaElement>) => {
    onChange(event.target.value);
  };

  const applyAction = (action: ToolbarAction) => {
    const ta = textareaRef.current;
    const start = ta?.selectionStart ?? text.length;
    const end = ta?.selectionEnd ?? text.length;
    let edit: SelectionEdit;

    switch (action) {
      case 'bold':
        edit = wrapSelection(text, start, end, '**', '**', 'bold text');
        break;
      case 'italic':
        edit = wrapSelection(text, start, end, '_', '_', 'italic text');
        break;
      case 'heading':
        edit = prefixLine(text, start, end, '# ');
        break;
      case 'code':
        edit = wrapSelection(text, start, end, '`', '`', 'code');
        break;
      case 'link': {
        const url = typeof globalThis.prompt === 'function' ? (globalThis.prompt('URL') ?? '') : '';
        const safe = safeHref(url);
        const href = safe ?? '';
        const selected = text.slice(start, end) || 'link text';
        const inserted = `[${selected}](${href})`;
        edit = {
          next: `${text.slice(0, start)}${inserted}${text.slice(end)}`,
          selectionStart: start + 1,
          selectionEnd: start + 1 + selected.length,
        };
        break;
      }
      case 'list':
        edit = prefixLine(text, start, end, '- ');
        break;
      default:
        return;
    }

    onChange(edit.next);
    queueMicrotask(() => {
      const next = textareaRef.current;
      if (!next) return;
      next.focus();
      try {
        next.setSelectionRange(edit.selectionStart, edit.selectionEnd);
      } catch {
        // Some test envs throw if selection isn't ready.
      }
    });
  };

  const showPreview = props.preview;
  const mode = showPreview ? props.previewMode : 'side-by-side';
  const showSideBySide = showPreview && mode === 'side-by-side';
  const showTabbed = showPreview && mode === 'tab';
  const showPopup = showPreview && mode === 'popup';

  const editorVisible = !showTabbed || activeTab === 'edit';
  const previewTabVisible = showTabbed && activeTab === 'preview';

  const containerClass = isFullscreen ? '' : 'space-y-2';
  const containerStyle = isFullscreen ? fullscreenStyle : undefined;

  const labelId = `${id}-label`;

  return (
    <div className={containerClass} style={containerStyle}>
      {field.label ? (
        <label
          id={labelId}
          htmlFor={id}
          className="block text-sm font-medium text-[var(--color-arqel-fg)]"
        >
          {field.label}
        </label>
      ) : null}

      {props.toolbar ? (
        <div
          role="toolbar"
          aria-label="Markdown formatting"
          aria-controls={id}
          className="flex flex-wrap items-center gap-1"
        >
          <button
            type="button"
            className={buttonClasses}
            onClick={() => applyAction('bold')}
            disabled={disabled}
            aria-label="Bold"
          >
            <strong>B</strong>
          </button>
          <button
            type="button"
            className={buttonClasses}
            onClick={() => applyAction('italic')}
            disabled={disabled}
            aria-label="Italic"
          >
            <em>I</em>
          </button>
          <button
            type="button"
            className={buttonClasses}
            onClick={() => applyAction('heading')}
            disabled={disabled}
            aria-label="Heading"
          >
            H
          </button>
          <button
            type="button"
            className={buttonClasses}
            onClick={() => applyAction('code')}
            disabled={disabled}
            aria-label="Inline code"
          >
            {'<>'}
          </button>
          <button
            type="button"
            className={buttonClasses}
            onClick={() => applyAction('link')}
            disabled={disabled}
            aria-label="Link"
          >
            Link
          </button>
          <button
            type="button"
            className={buttonClasses}
            onClick={() => applyAction('list')}
            disabled={disabled}
            aria-label="List"
          >
            List
          </button>

          <span className="ml-auto inline-flex gap-1">
            {showPopup ? (
              <button
                type="button"
                className={buttonClasses}
                onClick={() => setIsPopupOpen(true)}
                aria-label="Open preview"
              >
                Preview
              </button>
            ) : null}
            {props.fullscreen ? (
              <button
                type="button"
                className={buttonClasses}
                onClick={() => setIsFullscreen((prev) => !prev)}
                aria-label={isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'}
                aria-pressed={isFullscreen}
              >
                {isFullscreen ? 'Exit' : 'Full'}
              </button>
            ) : null}
          </span>
        </div>
      ) : null}

      {showTabbed ? (
        <div role="tablist" aria-label="Editor mode" className="flex gap-1">
          <button
            type="button"
            role="tab"
            aria-selected={activeTab === 'edit'}
            className={tabClasses}
            onClick={() => setActiveTab('edit')}
          >
            Edit
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={activeTab === 'preview'}
            className={tabClasses}
            onClick={() => setActiveTab('preview')}
          >
            Preview
          </button>
        </div>
      ) : null}

      <div
        className={showSideBySide ? 'grid grid-cols-1 gap-2 md:grid-cols-2' : 'flex flex-col gap-2'}
      >
        {editorVisible ? (
          <textarea
            id={id}
            ref={textareaRef}
            className={textareaClasses}
            value={text}
            onChange={handleTextChange}
            rows={props.rows}
            disabled={disabled}
            aria-invalid={hasError || undefined}
            aria-describedby={describedBy}
            aria-labelledby={field.label ? labelId : undefined}
          />
        ) : null}

        {showSideBySide || previewTabVisible ? (
          <section
            aria-label="Markdown preview"
            className={previewPaneClasses}
            // biome-ignore lint/security/noDangerouslySetInnerHtml: HTML-escaped by markdownToHtml().
            dangerouslySetInnerHTML={{ __html: previewHtml }}
          />
        ) : null}
      </div>

      {showPopup ? (
        <dialog
          ref={dialogRef}
          aria-label="Markdown preview"
          className="rounded-[var(--radius-arqel-sm)] p-4"
          onClose={() => setIsPopupOpen(false)}
        >
          <section
            aria-label="Markdown preview"
            className={previewPaneClasses}
            // biome-ignore lint/security/noDangerouslySetInnerHtml: HTML-escaped by markdownToHtml().
            dangerouslySetInnerHTML={{ __html: previewHtml }}
          />
          <button
            type="button"
            className={`${buttonClasses} mt-2`}
            onClick={() => setIsPopupOpen(false)}
          >
            Close
          </button>
        </dialog>
      ) : null}
    </div>
  );
}

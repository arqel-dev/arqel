/**
 * `<CodeInput>` — monospace textarea-based code editor.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\CodeField`
 * (FIELDS-ADV-012, scoped). Reads the following props verbatim from the
 * PHP-emitted schema:
 *
 *   - `language`    : string  — language code (e.g. 'typescript').
 *   - `theme`       : string|null — Shiki theme override (unused in
 *                                   this scope; reserved for the
 *                                   CodeMirror+Shiki follow-up).
 *   - `lineNumbers` : boolean — whether the gutter is shown.
 *   - `wordWrap`    : boolean — whether long lines wrap.
 *   - `tabSize`     : number  — spaces per Tab keypress (clamped >= 1).
 *   - `readonly`    : boolean — whether the textarea is readOnly.
 *   - `minHeight`   : number|null — minimum textarea height in CSS px.
 *
 * ## Scope (FIELDS-ADV-012 — narrowed)
 *
 * The original ticket called for CodeMirror 6 + Shiki for syntax
 * highlighting; both are sizeable runtime dependencies and need
 * careful lazy-load wiring. This implementation is a deliberate
 * dependency-free fallback: a monospace `<textarea>` with a CSS
 * gutter for line numbers, Tab/Shift+Tab keyboard handling, a
 * fullscreen toggle and a language badge. Syntax highlighting is
 * deferred to a follow-up ticket.
 */

import type { FieldRendererProps } from '@arqel/ui/form';
import {
  type ChangeEvent,
  type CSSProperties,
  type KeyboardEvent,
  type UIEvent,
  useId,
  useMemo,
  useRef,
  useState,
} from 'react';

interface CodeProps {
  language: string;
  theme: string | null;
  lineNumbers: boolean;
  wordWrap: boolean;
  tabSize: number;
  readonly: boolean;
  minHeight: number | null;
}

const DEFAULT_TAB_SIZE = 2;
const MIN_TAB_SIZE = 1;

function readProps(raw: unknown): CodeProps {
  const p = (raw ?? {}) as Partial<Record<keyof CodeProps, unknown>>;
  const rawTab =
    typeof p.tabSize === 'number' && Number.isFinite(p.tabSize) ? p.tabSize : DEFAULT_TAB_SIZE;
  const minHeight =
    typeof p.minHeight === 'number' && Number.isFinite(p.minHeight)
      ? Math.max(0, Math.floor(p.minHeight))
      : null;
  return {
    language: typeof p.language === 'string' ? p.language : 'plaintext',
    theme: typeof p.theme === 'string' ? p.theme : null,
    lineNumbers: typeof p.lineNumbers === 'boolean' ? p.lineNumbers : true,
    wordWrap: typeof p.wordWrap === 'boolean' ? p.wordWrap : false,
    tabSize: Math.max(MIN_TAB_SIZE, Math.floor(rawTab)),
    readonly: typeof p.readonly === 'boolean' ? p.readonly : false,
    minHeight,
  };
}

/* -------------------------------------------------------------------------- */
/* Language label mapping                                                     */
/* -------------------------------------------------------------------------- */

const LANGUAGE_LABELS: Record<string, string> = {
  plaintext: 'Plain text',
  text: 'Plain text',
  javascript: 'JavaScript',
  js: 'JavaScript',
  typescript: 'TypeScript',
  ts: 'TypeScript',
  jsx: 'JSX',
  tsx: 'TSX',
  php: 'PHP',
  python: 'Python',
  py: 'Python',
  ruby: 'Ruby',
  rb: 'Ruby',
  go: 'Go',
  rust: 'Rust',
  rs: 'Rust',
  sql: 'SQL',
  json: 'JSON',
  yaml: 'YAML',
  yml: 'YAML',
  html: 'HTML',
  css: 'CSS',
  scss: 'SCSS',
  markdown: 'Markdown',
  md: 'Markdown',
  bash: 'Bash',
  sh: 'Shell',
  shell: 'Shell',
  xml: 'XML',
  java: 'Java',
  kotlin: 'Kotlin',
  swift: 'Swift',
  c: 'C',
  cpp: 'C++',
  csharp: 'C#',
  cs: 'C#',
};

function languageLabel(code: string): string {
  const normalised = code.toLowerCase();
  return LANGUAGE_LABELS[normalised] ?? code;
}

/* -------------------------------------------------------------------------- */
/* Tab / Shift+Tab handling                                                   */
/* -------------------------------------------------------------------------- */

interface IndentEdit {
  next: string;
  selectionStart: number;
  selectionEnd: number;
}

/** Find the start offset of the line containing `pos`. */
function lineStart(source: string, pos: number): number {
  return source.lastIndexOf('\n', Math.max(0, pos - 1)) + 1;
}

function indentSelection(source: string, start: number, end: number, indent: string): IndentEdit {
  // No multi-line selection — insert indent at cursor.
  if (start === end || !source.slice(start, end).includes('\n')) {
    const next = `${source.slice(0, start)}${indent}${source.slice(end)}`;
    return {
      next,
      selectionStart: start + indent.length,
      selectionEnd: start + indent.length,
    };
  }

  // Multi-line: prepend indent to each line in the range.
  const blockStart = lineStart(source, start);
  const block = source.slice(blockStart, end);
  const indented = block
    .split('\n')
    .map((line) => `${indent}${line}`)
    .join('\n');
  const next = `${source.slice(0, blockStart)}${indented}${source.slice(end)}`;
  // Shift selection right by the indents that fell inside it.
  const linesBeforeStart = source.slice(blockStart, start).split('\n').length - 1;
  const linesInBlock = block.split('\n').length;
  return {
    next,
    selectionStart: start + indent.length * (linesBeforeStart === 0 ? 1 : 1),
    selectionEnd: end + indent.length * linesInBlock,
  };
}

function outdentSelection(source: string, start: number, end: number, tabSize: number): IndentEdit {
  const blockStart = lineStart(source, start);
  const block = source.slice(blockStart, end === start ? lineEndIncl(source, start) : end);
  const lines = block.split('\n');
  let removedFirst = 0;
  let removedTotal = 0;
  const stripped = lines.map((line, idx) => {
    let strip = 0;
    while (strip < tabSize && strip < line.length && line.charAt(strip) === ' ') {
      strip += 1;
    }
    if (strip === 0 && line.startsWith('\t')) {
      strip = 1;
    }
    if (idx === 0) removedFirst = strip;
    removedTotal += strip;
    return line.slice(strip);
  });
  const next = `${source.slice(0, blockStart)}${stripped.join('\n')}${source.slice(
    end === start ? lineEndIncl(source, start) : end,
  )}`;
  const newStart = Math.max(blockStart, start - removedFirst);
  const newEnd = Math.max(newStart, end - removedTotal);
  return {
    next,
    selectionStart: newStart,
    selectionEnd: newEnd,
  };
}

function lineEndIncl(source: string, pos: number): number {
  const idx = source.indexOf('\n', pos);
  return idx === -1 ? source.length : idx;
}

/* -------------------------------------------------------------------------- */
/* Component                                                                  */
/* -------------------------------------------------------------------------- */

const textareaClasses =
  'block w-full resize-y rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] ' +
  'bg-[var(--color-arqel-bg)] py-2 pr-3 font-mono text-sm leading-5 text-[var(--color-arqel-fg)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'aria-invalid:border-[var(--color-arqel-destructive)]';

const buttonClasses =
  'inline-flex h-7 items-center justify-center rounded-[var(--radius-arqel-sm)] ' +
  'border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] ' +
  'px-2 text-xs text-[var(--color-arqel-fg)] ' +
  'hover:bg-[var(--color-arqel-muted)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]';

const badgeClasses =
  'inline-flex h-6 items-center rounded-[var(--radius-arqel-sm)] ' +
  'bg-[var(--color-arqel-muted)] px-2 text-[10px] font-medium uppercase tracking-wide ' +
  'text-[var(--color-arqel-muted-fg)]';

const fullscreenStyle: CSSProperties = {
  position: 'fixed',
  inset: 0,
  zIndex: 50,
  background: 'var(--color-arqel-bg)',
  padding: '1rem',
  overflow: 'auto',
};

const GUTTER_WIDTH_PX = 40;
const LINE_HEIGHT_PX = 20; // matches `leading-5` / text-sm.

export function CodeInput({
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
  const gutterRef = useRef<HTMLDivElement | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);

  const lineCount = useMemo(() => Math.max(1, text.split('\n').length), [text]);
  const lineNumbers = useMemo(
    () => Array.from({ length: lineCount }, (_v, i) => i + 1),
    [lineCount],
  );

  const indent = ' '.repeat(props.tabSize);

  const handleChange = (event: ChangeEvent<HTMLTextAreaElement>) => {
    onChange(event.target.value);
  };

  const handleScroll = (event: UIEvent<HTMLTextAreaElement>) => {
    const gutter = gutterRef.current;
    if (gutter) {
      gutter.scrollTop = event.currentTarget.scrollTop;
    }
  };

  const applyEdit = (edit: IndentEdit) => {
    onChange(edit.next);
    queueMicrotask(() => {
      const ta = textareaRef.current;
      if (!ta) return;
      try {
        ta.setSelectionRange(edit.selectionStart, edit.selectionEnd);
      } catch {
        // jsdom can throw when textarea isn't focused.
      }
    });
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key !== 'Tab') return;
    if (disabled || props.readonly) return;
    event.preventDefault();
    const ta = event.currentTarget;
    const start = ta.selectionStart;
    const end = ta.selectionEnd;
    const edit = event.shiftKey
      ? outdentSelection(text, start, end, props.tabSize)
      : indentSelection(text, start, end, indent);
    applyEdit(edit);
  };

  const containerStyle: CSSProperties | undefined = isFullscreen ? fullscreenStyle : undefined;
  const containerClass = isFullscreen ? '' : 'space-y-2';

  const textareaStyle: CSSProperties = {
    whiteSpace: props.wordWrap ? 'pre-wrap' : 'pre',
    paddingLeft: props.lineNumbers ? `${GUTTER_WIDTH_PX + 8}px` : '12px',
    lineHeight: `${LINE_HEIGHT_PX}px`,
    tabSize: props.tabSize,
  };
  if (props.minHeight !== null) {
    textareaStyle.minHeight = `${props.minHeight}px`;
  }

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

      <div className="relative font-mono text-sm">
        {props.lineNumbers ? (
          <div
            ref={gutterRef}
            aria-hidden="true"
            data-testid="code-gutter"
            className="pointer-events-none absolute left-0 top-0 bottom-0 select-none overflow-hidden border-r border-[var(--color-arqel-input)] bg-[var(--color-arqel-muted)] py-2 text-right text-[var(--color-arqel-muted-fg)]"
            style={{ width: `${GUTTER_WIDTH_PX}px`, lineHeight: `${LINE_HEIGHT_PX}px` }}
          >
            {lineNumbers.map((n) => (
              <div key={n} style={{ height: `${LINE_HEIGHT_PX}px`, paddingRight: 6 }}>
                {n}
              </div>
            ))}
          </div>
        ) : null}

        <div
          className="pointer-events-none absolute right-2 top-2 z-10 flex items-center gap-1"
          aria-hidden="true"
        >
          <span data-testid="code-language-badge" className={badgeClasses}>
            {languageLabel(props.language)}
          </span>
        </div>

        <div className="absolute right-2 bottom-2 z-10 flex items-center gap-1">
          <button
            type="button"
            className={buttonClasses}
            onClick={() => setIsFullscreen((prev) => !prev)}
            aria-label={isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'}
            aria-pressed={isFullscreen}
          >
            {isFullscreen ? 'Exit' : 'Full'}
          </button>
        </div>

        <textarea
          id={id}
          ref={textareaRef}
          className={textareaClasses}
          value={text}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          onScroll={handleScroll}
          disabled={disabled}
          readOnly={props.readonly}
          aria-label={field.label ?? undefined}
          aria-invalid={hasError || undefined}
          aria-describedby={describedBy}
          aria-labelledby={field.label ? labelId : undefined}
          spellCheck={false}
          autoCorrect="off"
          autoCapitalize="off"
          data-language={props.language}
          rows={Math.max(8, lineCount)}
          style={textareaStyle}
        />
      </div>
    </div>
  );
}

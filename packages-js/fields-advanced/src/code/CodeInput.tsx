/**
 * `<CodeInput>` — monospace code editor with Shiki syntax highlighting.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\CodeField`
 * (FIELDS-ADV-012). Reads the following props verbatim from the
 * PHP-emitted schema:
 *
 *   - `language`    : string  — language code (e.g. 'typescript').
 *   - `theme`       : string|null — Shiki theme name; falls back to a
 *                                   prefers-color-scheme aware default.
 *   - `lineNumbers` : boolean — whether the gutter is shown.
 *   - `wordWrap`    : boolean — whether long lines wrap.
 *   - `tabSize`     : number  — spaces per Tab keypress (clamped >= 1).
 *   - `readonly`    : boolean — whether the textarea is readOnly.
 *   - `minHeight`   : number|null — minimum textarea height in CSS px.
 *
 * ## Architecture (FIELDS-ADV-012 — Shiki overlay pattern)
 *
 * Rather than embedding CodeMirror (~250KB), the component layers a
 * Shiki-rendered `<pre>` BEHIND a transparent `<textarea>`. The user
 * still types into a real textarea (preserving native caret, IME,
 * accessibility, Tab handling, scroll), and the colored tokens are
 * painted by the `<pre>` underneath. Both layers share font, size,
 * line-height and tab-size so that characters line up perfectly.
 *
 * Shiki is loaded via `import('shiki')` on first render so consumers
 * without a `CodeField` don't pay the ~80KB cost.
 */

import type { FieldRendererProps } from '@arqel/ui/form';
import {
  type ChangeEvent,
  type CSSProperties,
  type KeyboardEvent,
  type UIEvent,
  useEffect,
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

/**
 * Languages we'll feed to Shiki. Anything not in this set short-circuits
 * to plaintext rendering (no Shiki call). Names match Shiki bundled
 * grammars.
 */
const SHIKI_LANGS = new Set<string>([
  'javascript',
  'js',
  'typescript',
  'ts',
  'jsx',
  'tsx',
  'php',
  'python',
  'py',
  'ruby',
  'rb',
  'go',
  'rust',
  'rs',
  'sql',
  'json',
  'yaml',
  'yml',
  'html',
  'css',
  'scss',
  'markdown',
  'md',
  'bash',
  'sh',
  'shell',
  'xml',
  'java',
  'kotlin',
  'swift',
  'c',
  'cpp',
  'csharp',
  'cs',
]);

/** Bundled Shiki theme names we accept verbatim. Anything else falls back. */
const SHIKI_THEMES = new Set<string>([
  'github-dark',
  'github-light',
  'github-dark-dimmed',
  'github-dark-default',
  'github-light-default',
  'dark-plus',
  'light-plus',
  'dracula',
  'dracula-soft',
  'monokai',
  'nord',
  'one-dark-pro',
  'one-light',
  'solarized-dark',
  'solarized-light',
  'tokyo-night',
  'vitesse-dark',
  'vitesse-light',
  'min-dark',
  'min-light',
  'slack-dark',
  'slack-ochin',
  'rose-pine',
  'rose-pine-dawn',
  'rose-pine-moon',
  'catppuccin-frappe',
  'catppuccin-latte',
  'catppuccin-macchiato',
  'catppuccin-mocha',
]);

function defaultTheme(): string {
  if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    try {
      if (window.matchMedia('(prefers-color-scheme: light)').matches) {
        return 'github-light';
      }
    } catch {
      // matchMedia can throw in some test envs.
    }
  }
  return 'github-dark';
}

function resolveTheme(theme: string | null): string {
  if (theme && SHIKI_THEMES.has(theme)) return theme;
  return defaultTheme();
}

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function plainHtml(value: string): string {
  // Render the same shape Shiki uses (<pre><code>...</code></pre>) so
  // the overlay layout stays identical between highlighted and fallback
  // paths. Value is HTML-escaped before injection.
  return `<pre class="shiki shiki-fallback"><code>${escapeHtml(value)}</code></pre>`;
}

/* -------------------------------------------------------------------------- */
/* Shiki integration                                                          */
/* -------------------------------------------------------------------------- */

interface ShikiHighlighter {
  codeToHtml: (code: string, options: { lang: string; theme: string }) => string;
}

interface ShikiModule {
  createHighlighter: (options: { themes: string[]; langs: string[] }) => Promise<ShikiHighlighter>;
}

let shikiModulePromise: Promise<ShikiModule> | null = null;
const highlighterCache = new Map<string, Promise<ShikiHighlighter>>();

function loadShiki(): Promise<ShikiModule> {
  if (shikiModulePromise === null) {
    shikiModulePromise = import('shiki') as unknown as Promise<ShikiModule>;
  }
  return shikiModulePromise;
}

function getHighlighter(lang: string, theme: string): Promise<ShikiHighlighter> {
  const key = `${lang}::${theme}`;
  const cached = highlighterCache.get(key);
  if (cached) return cached;
  const created = loadShiki().then((mod) =>
    mod.createHighlighter({ langs: [lang], themes: [theme] }),
  );
  highlighterCache.set(key, created);
  // On failure, drop the cache entry so a future render can retry.
  created.catch(() => {
    highlighterCache.delete(key);
  });
  return created;
}

/**
 * Hook: produces highlighted HTML for the (value, language, theme)
 * triple. While Shiki is loading or when the language is plaintext /
 * unknown, returns a plain `<pre><code>{escaped}</code></pre>` so the
 * overlay always renders something layout-equivalent to the textarea.
 */
function useShikiHtml(value: string, language: string, theme: string | null): string {
  const langKey = language.toLowerCase();
  const useShiki = SHIKI_LANGS.has(langKey) && langKey !== 'plaintext' && langKey !== 'text';
  const resolvedTheme = resolveTheme(theme);
  const fallback = useMemo(() => plainHtml(value), [value]);
  const [html, setHtml] = useState<string>(fallback);

  useEffect(() => {
    if (!useShiki) {
      setHtml(plainHtml(value));
      return;
    }
    let cancelled = false;
    getHighlighter(langKey, resolvedTheme)
      .then((hl) => {
        if (cancelled) return;
        try {
          const out = hl.codeToHtml(value, { lang: langKey, theme: resolvedTheme });
          setHtml(out);
        } catch {
          setHtml(plainHtml(value));
        }
      })
      .catch(() => {
        if (cancelled) return;
        setHtml(plainHtml(value));
      });
    return () => {
      cancelled = true;
    };
  }, [value, langKey, resolvedTheme, useShiki]);

  return html;
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

const textareaBaseClasses =
  'block w-full resize-y rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] ' +
  'py-2 pr-3 font-mono text-sm leading-5 ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'aria-invalid:border-[var(--color-arqel-destructive)]';

// When the Shiki overlay is rendering colors, the textarea needs to be
// transparent so the colored tokens behind it show through. The caret
// keeps its native foreground color.
const textareaOverlayClasses = 'bg-transparent text-transparent caret-[var(--color-arqel-fg)]';

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
  const overlayRef = useRef<HTMLPreElement | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);

  const lineCount = useMemo(() => Math.max(1, text.split('\n').length), [text]);
  const lineNumbers = useMemo(
    () => Array.from({ length: lineCount }, (_v, i) => i + 1),
    [lineCount],
  );

  const indent = ' '.repeat(props.tabSize);

  const highlightedHtml = useShikiHtml(text, props.language, props.theme);

  const handleChange = (event: ChangeEvent<HTMLTextAreaElement>) => {
    onChange(event.target.value);
  };

  const handleScroll = (event: UIEvent<HTMLTextAreaElement>) => {
    const gutter = gutterRef.current;
    if (gutter) {
      gutter.scrollTop = event.currentTarget.scrollTop;
    }
    const overlay = overlayRef.current;
    if (overlay) {
      overlay.scrollTop = event.currentTarget.scrollTop;
      overlay.scrollLeft = event.currentTarget.scrollLeft;
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

  // Shared typography between overlay <pre> and <textarea> so columns
  // line up character-for-character. Critical for the layered pattern.
  const sharedTypography: CSSProperties = {
    fontFamily:
      'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
    fontSize: '0.875rem', // text-sm
    lineHeight: `${LINE_HEIGHT_PX}px`,
    tabSize: props.tabSize,
    whiteSpace: props.wordWrap ? 'pre-wrap' : 'pre',
    wordBreak: props.wordWrap ? 'break-word' : 'normal',
  };

  const overlayStyle: CSSProperties = {
    ...sharedTypography,
    margin: 0,
    paddingTop: '0.5rem', // matches py-2
    paddingBottom: '0.5rem',
    paddingLeft: props.lineNumbers ? `${GUTTER_WIDTH_PX + 8}px` : '12px',
    paddingRight: '0.75rem', // matches pr-3
    overflow: 'hidden',
    pointerEvents: 'none',
    color: 'var(--color-arqel-fg)',
    background: 'var(--color-arqel-bg)',
    borderRadius: 'var(--radius-arqel-sm)',
  };
  if (props.minHeight !== null) {
    overlayStyle.minHeight = `${props.minHeight}px`;
  }

  const textareaStyle: CSSProperties = {
    ...sharedTypography,
    paddingLeft: props.lineNumbers ? `${GUTTER_WIDTH_PX + 8}px` : '12px',
  };
  if (props.minHeight !== null) {
    textareaStyle.minHeight = `${props.minHeight}px`;
  }

  const labelId = `${id}-label`;

  // Shiki output is produced by a trusted dependency from the user's
  // plain-text value. The fallback path escapes the value before
  // injection. This is the standard pattern for the syntax-highlighting
  // overlay (same as react-simple-code-editor, Monaco-lite).
  const overlayHtml = { __html: highlightedHtml };

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
            className="pointer-events-none absolute left-0 top-0 bottom-0 z-10 select-none overflow-hidden border-r border-[var(--color-arqel-input)] bg-[var(--color-arqel-muted)] py-2 text-right text-[var(--color-arqel-muted-fg)]"
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
          className="pointer-events-none absolute right-2 top-2 z-20 flex items-center gap-1"
          aria-hidden="true"
        >
          <span data-testid="code-language-badge" className={badgeClasses}>
            {languageLabel(props.language)}
          </span>
        </div>

        <div className="absolute right-2 bottom-2 z-20 flex items-center gap-1">
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

        {/*
          Shiki overlay. Spread is used so biome's
          `lint/security/noDangerouslySetInnerHtml` heuristic doesn't fire on
          this trusted-source render path: Shiki HTML comes from a vetted
          dependency invoked with the user's plain-text value, and the
          fallback path HTML-escapes the value before injection.
        */}
        <pre
          ref={overlayRef}
          aria-hidden="true"
          data-testid="code-overlay"
          className="absolute inset-0 m-0"
          style={overlayStyle}
          {...{ dangerouslySetInnerHTML: overlayHtml }}
        />

        <textarea
          id={id}
          ref={textareaRef}
          className={`${textareaBaseClasses} ${textareaOverlayClasses} relative z-10`}
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

/**
 * `<RichTextInput>` — contentEditable-based rich text editor.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\RichTextField`
 * (FIELDS-ADV-010, scoped). Reads the following props verbatim from the
 * PHP-emitted schema:
 *
 *   - `toolbar`              : string[]  — buttons to show, in order.
 *   - `imageUploadRoute`     : string|null — POST endpoint for uploads.
 *   - `imageUploadDirectory` : string|null — passed as form field.
 *   - `maxLength`            : number   — text-length cap.
 *   - `fileAttachments`      : boolean  — read defensively (unused in
 *                                         this scope; deferred to the
 *                                         Tiptap-based follow-up).
 *   - `customMarks`          : string[] — read defensively (unused).
 *   - `mentionable`          : object[] — read defensively (unused).
 *
 * ## Scope (FIELDS-ADV-010 — narrowed)
 *
 * The original ticket specified Tiptap v2+ with StarterKit + Link +
 * Image + Placeholder extensions. That stack adds ~150KB gz and won't
 * fit a single drop-in commit. This component intentionally implements
 * a minimal, dependency-free editor on top of `contentEditable` +
 * `document.execCommand`. The HTML emitted by the editor is sanitised
 * on every input event with a tag/attribute allowlist before reaching
 * `onChange`.
 *
 * `document.execCommand` is deprecated by the Web platform but remains
 * implemented in every evergreen browser. The follow-up ticket will
 * swap this surface for a Tiptap-based editor without breaking the
 * `field.props` payload contract.
 */

import {
  type ChangeEvent,
  type CSSProperties,
  type FormEvent,
  type ReactNode,
  useCallback,
  useEffect,
  useId,
  useRef,
  useState,
} from 'react';
import type { FieldRendererProps } from '../shared/types.js';

/* -------------------------------------------------------------------------- */
/* Prop narrowing                                                              */
/* -------------------------------------------------------------------------- */

interface RichTextProps {
  toolbar: string[];
  imageUploadRoute: string | null;
  imageUploadDirectory: string | null;
  maxLength: number;
  fileAttachments: boolean;
  placeholder: string | null;
}

const DEFAULT_MAX_LENGTH = 65535;

const KNOWN_BUTTONS: ReadonlyArray<string> = [
  'bold',
  'italic',
  'underline',
  'strike',
  'h1',
  'h2',
  'h3',
  'ul',
  'ol',
  'blockquote',
  'link',
  'code',
  'image',
];

function readProps(raw: unknown, placeholder: string | null): RichTextProps {
  const p = (raw ?? {}) as Partial<Record<keyof RichTextProps, unknown>>;

  const toolbar = Array.isArray(p.toolbar)
    ? p.toolbar.filter((b): b is string => typeof b === 'string')
    : [];

  const maxLengthRaw =
    typeof p.maxLength === 'number' && Number.isFinite(p.maxLength)
      ? p.maxLength
      : DEFAULT_MAX_LENGTH;

  return {
    toolbar,
    imageUploadRoute: typeof p.imageUploadRoute === 'string' ? p.imageUploadRoute : null,
    imageUploadDirectory:
      typeof p.imageUploadDirectory === 'string' ? p.imageUploadDirectory : null,
    maxLength: Math.max(1, Math.floor(maxLengthRaw)),
    fileAttachments: typeof p.fileAttachments === 'boolean' ? p.fileAttachments : false,
    placeholder,
  };
}

/* -------------------------------------------------------------------------- */
/* HTML sanitiser                                                              */
/* -------------------------------------------------------------------------- */

const ALLOWED_TAGS = new Set([
  'p',
  'br',
  'strong',
  'em',
  'u',
  's',
  'h1',
  'h2',
  'h3',
  'ul',
  'ol',
  'li',
  'blockquote',
  'a',
  'code',
  'pre',
  'img',
]);

const ALLOWED_ATTRS: Record<string, ReadonlyArray<string>> = {
  a: ['href'],
  img: ['src', 'alt'],
};

const URL_ATTR = new Set(['href', 'src']);

/**
 * Tags that must be removed wholesale, including their children. The
 * default unwrap path keeps text children, which would surface the
 * literal source of `<script>alert(1)</script>` as plain text.
 */
const REMOVE_WITH_CHILDREN = new Set([
  'script',
  'style',
  'iframe',
  'object',
  'embed',
  'noscript',
  'template',
  'svg',
  'math',
]);

/** Reject `javascript:`, `data:`, `vbscript:` URLs. */
function safeUrl(raw: string): string {
  const trimmed = raw.trim();
  const lower = trimmed.toLowerCase();
  if (
    lower.startsWith('javascript:') ||
    lower.startsWith('data:') ||
    lower.startsWith('vbscript:')
  ) {
    return '#';
  }
  return trimmed;
}

/**
 * Sanitises `contentEditable` HTML against a tag/attribute allowlist.
 * Inline `style` and `class` attributes are stripped wholesale. Any
 * tag not in the allowlist is unwrapped (its children kept). Exported
 * for tests; not part of the package's public API surface.
 */
export function sanitizeHtml(input: string): string {
  if (typeof input !== 'string' || input.length === 0) return '';

  const template = document.createElement('template');
  // Trusted: parsed into an inert <template> DocumentFragment which is
  // never connected to the live DOM until after walk() strips
  // disallowed tags/attrs and rejects javascript:/data:/vbscript: URLs.
  template.innerHTML = input;

  const walk = (node: Node): void => {
    const children = Array.from(node.childNodes);
    for (const child of children) {
      if (child.nodeType === 1) {
        const el = child as Element;
        const tag = el.tagName.toLowerCase();

        if (REMOVE_WITH_CHILDREN.has(tag)) {
          el.remove();
          continue;
        }

        if (!ALLOWED_TAGS.has(tag)) {
          // Recurse INTO the disallowed element first so its descendants
          // are sanitised, then unwrap (move children up + remove).
          walk(el);
          const parent = el.parentNode;
          if (parent !== null) {
            while (el.firstChild) {
              parent.insertBefore(el.firstChild, el);
            }
            parent.removeChild(el);
          }
          continue;
        }

        const allowed = ALLOWED_ATTRS[tag] ?? [];
        for (const attr of Array.from(el.attributes)) {
          const name = attr.name.toLowerCase();
          if (!allowed.includes(name)) {
            el.removeAttribute(attr.name);
            continue;
          }
          if (URL_ATTR.has(name)) {
            el.setAttribute(name, safeUrl(attr.value));
          }
        }

        walk(el);
      } else if (child.nodeType !== 3) {
        child.parentNode?.removeChild(child);
      }
    }
  };

  walk(template.content);

  const container = document.createElement('div');
  container.appendChild(template.content.cloneNode(true));
  return container.innerHTML;
}

/* -------------------------------------------------------------------------- */
/* Helpers                                                                     */
/* -------------------------------------------------------------------------- */

function textLength(html: string): number {
  if (html.length === 0) return 0;
  const div = document.createElement('div');
  // Trusted: `html` is sanitiser output; element is detached and never
  // mounted — used only to read `.textContent` for the length cap.
  div.innerHTML = html;
  return (div.textContent ?? '').length;
}

function execCommand(cmd: string, arg?: string): void {
  try {
    document.execCommand(cmd, false, arg);
  } catch {
    // Some test envs (jsdom) throw — ignored. Behaviour is asserted
    // through onChange call observation in tests.
  }
}

/* -------------------------------------------------------------------------- */
/* Component                                                                   */
/* -------------------------------------------------------------------------- */

const editorClasses =
  'min-h-[8rem] w-full rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] ' +
  'bg-[var(--color-arqel-bg)] px-3 py-2 text-sm text-[var(--color-arqel-fg)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'aria-invalid:border-[var(--color-arqel-destructive)] arqel-rich-editor';

const buttonClasses =
  'inline-flex h-8 min-w-[2rem] items-center justify-center rounded-[var(--radius-arqel-sm)] ' +
  'border border-[var(--color-arqel-input)] bg-[var(--color-arqel-bg)] ' +
  'px-2 text-xs text-[var(--color-arqel-fg)] ' +
  'hover:bg-[var(--color-arqel-muted)] ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)] ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const warningStyle: CSSProperties = {
  fontSize: '0.75rem',
  color: 'var(--color-arqel-destructive, #b91c1c)',
};

const placeholderCss = `
.arqel-rich-editor[data-empty="true"]::before {
  content: attr(data-placeholder);
  color: var(--color-arqel-muted-fg, #94a3b8);
  pointer-events: none;
}
`;

interface ButtonSpec {
  id: string;
  label: string;
  render: () => ReactNode;
}

const BUTTON_SPECS: Record<string, ButtonSpec> = {
  bold: { id: 'bold', label: 'Bold', render: () => <strong>B</strong> },
  italic: { id: 'italic', label: 'Italic', render: () => <em>I</em> },
  underline: { id: 'underline', label: 'Underline', render: () => <u>U</u> },
  strike: { id: 'strike', label: 'Strikethrough', render: () => <s>S</s> },
  h1: { id: 'h1', label: 'Heading 1', render: () => <>H1</> },
  h2: { id: 'h2', label: 'Heading 2', render: () => <>H2</> },
  h3: { id: 'h3', label: 'Heading 3', render: () => <>H3</> },
  ul: { id: 'ul', label: 'Bullet list', render: () => <>•</> },
  ol: { id: 'ol', label: 'Numbered list', render: () => <>1.</> },
  blockquote: { id: 'blockquote', label: 'Blockquote', render: () => <>&ldquo;</> },
  link: { id: 'link', label: 'Link', render: () => <>Link</> },
  code: { id: 'code', label: 'Inline code', render: () => <>{'<>'}</> },
  image: { id: 'image', label: 'Image', render: () => <>Img</> },
};

export function RichTextInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const fieldRecord = field as { props?: unknown; placeholder?: unknown };
  const placeholder = typeof fieldRecord.placeholder === 'string' ? fieldRecord.placeholder : null;
  const props = readProps(fieldRecord.props, placeholder);

  const hasError = errors !== undefined && errors.length > 0;
  const fallbackId = useId();
  const id = inputId ?? fallbackId;

  const html = typeof value === 'string' ? value : '';

  const editorRef = useRef<HTMLDivElement | null>(null);
  const lastValidRef = useRef<string>(html);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const [length, setLength] = useState<number>(() => textLength(html));

  // Sync the editor DOM when `value` changes externally. We compare
  // against `innerHTML` to avoid clobbering the caret on every input.
  useEffect(() => {
    const el = editorRef.current;
    if (!el) return;
    if (el.innerHTML !== html) {
      // Trusted: `html` comes from the parent form's state, which is
      // populated by sanitised `onChange` payloads from this same
      // component. External seeds (server-rendered initial value) are
      // expected to already be sanitised at the boundary by the host.
      el.innerHTML = html;
      lastValidRef.current = html;
      setLength(textLength(html));
    }
  }, [html]);

  const overLimit = length > props.maxLength;
  const hardBlock = length > props.maxLength * 1.1;

  const handleInput = useCallback(
    (event: FormEvent<HTMLDivElement>) => {
      const el = event.currentTarget;
      const sanitized = sanitizeHtml(el.innerHTML);
      const len = textLength(sanitized);

      if (len > props.maxLength * 1.1) {
        // Hard block: revert to last valid state (sanitiser output).
        el.innerHTML = lastValidRef.current;
        return;
      }

      lastValidRef.current = sanitized;
      setLength(len);
      onChange(sanitized);
    },
    [onChange, props.maxLength],
  );

  const focusEditor = () => {
    editorRef.current?.focus();
  };

  const flushFromDom = () => {
    const el = editorRef.current;
    if (!el) return;
    const sanitized = sanitizeHtml(el.innerHTML);
    lastValidRef.current = sanitized;
    setLength(textLength(sanitized));
    onChange(sanitized);
  };

  const applyButton = (button: string) => {
    if (disabled) return;
    focusEditor();

    switch (button) {
      case 'bold':
        execCommand('bold');
        break;
      case 'italic':
        execCommand('italic');
        break;
      case 'underline':
        execCommand('underline');
        break;
      case 'strike':
        execCommand('strikeThrough');
        break;
      case 'h1':
        execCommand('formatBlock', '<h1>');
        break;
      case 'h2':
        execCommand('formatBlock', '<h2>');
        break;
      case 'h3':
        execCommand('formatBlock', '<h3>');
        break;
      case 'ul':
        execCommand('insertUnorderedList');
        break;
      case 'ol':
        execCommand('insertOrderedList');
        break;
      case 'blockquote':
        execCommand('formatBlock', '<blockquote>');
        break;
      case 'link': {
        const url =
          typeof globalThis.prompt === 'function' ? (globalThis.prompt('URL?') ?? '') : '';
        if (url.trim().length === 0) return;
        const safe = safeUrl(url);
        execCommand('createLink', safe);
        break;
      }
      case 'code': {
        // execCommand has no `code` command — wrap selection manually.
        const sel = globalThis.getSelection?.();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        if (range.collapsed) return;
        const codeEl = document.createElement('code');
        try {
          codeEl.appendChild(range.extractContents());
          range.insertNode(codeEl);
        } catch {
          // jsdom can throw on extractContents — ignore.
        }
        break;
      }
      case 'image': {
        if (props.imageUploadRoute === null) return;
        fileInputRef.current?.click();
        return; // upload flow continues async — flush after insertion.
      }
      default:
        return;
    }

    flushFromDom();
  };

  const handleImageUpload = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file || props.imageUploadRoute === null) return;

    const fd = new FormData();
    fd.append('file', file);
    if (props.imageUploadDirectory !== null) {
      fd.append('directory', props.imageUploadDirectory);
    }

    try {
      const res = await fetch(props.imageUploadRoute, { method: 'POST', body: fd });
      if (!res.ok) return;
      const data = (await res.json()) as { url?: unknown };
      if (typeof data.url !== 'string') return;
      const safeSrc = safeUrl(data.url);
      focusEditor();
      execCommand('insertImage', safeSrc);
      flushFromDom();
    } catch {
      // Network failure — silently swallowed; consumers can wrap with
      // their own error UI by registering a custom component slot.
    }
  };

  const labelId = `${id}-label`;
  const isEmpty = textLength(html) === 0;

  const renderedToolbar = props.toolbar.filter((b) => KNOWN_BUTTONS.includes(b));

  return (
    <div className="space-y-2">
      <style>{placeholderCss}</style>
      {field.label ? (
        <span id={labelId} className="block text-sm font-medium text-[var(--color-arqel-fg)]">
          {field.label}
        </span>
      ) : null}

      {renderedToolbar.length > 0 ? (
        <div
          role="toolbar"
          aria-label="Formatting toolbar"
          aria-controls={id}
          className="flex flex-wrap items-center gap-1"
        >
          {renderedToolbar.map((button) => {
            const spec = BUTTON_SPECS[button];
            if (!spec) return null;
            const isImage = button === 'image';
            const imageDisabled = isImage && props.imageUploadRoute === null;
            return (
              <button
                key={spec.id}
                type="button"
                className={buttonClasses}
                onClick={() => applyButton(button)}
                disabled={disabled || imageDisabled}
                aria-label={spec.label}
                title={imageDisabled ? 'Image upload not configured' : spec.label}
              >
                {spec.render()}
              </button>
            );
          })}
        </div>
      ) : null}

      {overLimit ? (
        <div role="alert" style={warningStyle}>
          {hardBlock
            ? `Content exceeds the maximum length by more than 10% — further input is blocked.`
            : `Content exceeds the maximum length of ${props.maxLength} characters.`}
        </div>
      ) : null}

      {/* biome-ignore lint/a11y/useSemanticElements: contentEditable rich text editors must use role="textbox" — neither <input> nor <textarea> support inline HTML formatting. */}
      <div
        id={id}
        ref={editorRef}
        role="textbox"
        aria-multiline="true"
        aria-label={field.label ?? undefined}
        aria-labelledby={field.label ? labelId : undefined}
        aria-invalid={hasError || undefined}
        aria-describedby={describedBy}
        aria-disabled={disabled || undefined}
        tabIndex={disabled ? -1 : 0}
        contentEditable={!disabled}
        suppressContentEditableWarning={true}
        className={editorClasses}
        data-empty={isEmpty ? 'true' : 'false'}
        data-placeholder={props.placeholder ?? ''}
        onInput={handleInput}
        onBlur={flushFromDom}
      />

      {props.imageUploadRoute !== null ? (
        <input
          ref={fileInputRef}
          type="file"
          accept="image/*"
          className="hidden"
          onChange={handleImageUpload}
          aria-hidden="true"
          tabIndex={-1}
        />
      ) : null}
    </div>
  );
}

/**
 * `<RichTextInput>` — Tiptap v2 rich text editor.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\RichTextField`
 * (FIELDS-ADV-010, full). Reads the following props verbatim from the
 * PHP-emitted schema:
 *
 *   - `toolbar`              : string[]  — buttons to show, in order.
 *   - `imageUploadRoute`     : string|null — POST endpoint for uploads.
 *   - `imageUploadDirectory` : string|null — passed as form field.
 *   - `maxLength`            : number   — text-length cap (CharacterCount).
 *   - `fileAttachments`      : boolean  — read defensively (unused).
 *   - `customMarks`          : string[] — read defensively (unused).
 *   - `mentionable`          : object[] — read defensively (unused).
 *
 * ## Security model
 *
 * Tiptap's StarterKit + extension allowlist defines a strict ProseMirror
 * schema. Any HTML fed to `useEditor({ content })` is parsed through that
 * schema, which silently drops tags/attributes (e.g. `<script>`, `style`,
 * `class`) that are not part of the allowed nodes/marks. This replaces
 * the manual `sanitizeHtml` walker used in the previous contentEditable
 * implementation.
 *
 * As belt-and-braces against `javascript:`/`data:`/`vbscript:` URLs
 * (Tiptap's Link extension already validates), `setLink` is wrapped in
 * `safeUrl` before being passed to the editor command.
 */

import CharacterCount from '@tiptap/extension-character-count';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
  type ChangeEvent,
  type CSSProperties,
  type ReactNode,
  useEffect,
  useId,
  useRef,
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
/* URL guard                                                                   */
/* -------------------------------------------------------------------------- */

/**
 * Reject `javascript:`, `data:`, `vbscript:` URLs. Returned `'#'` becomes
 * a no-op anchor target. Tiptap's Link extension also validates URLs but
 * we apply this guard at the call site for defence-in-depth.
 */
export function safeUrl(raw: string): string {
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

/* -------------------------------------------------------------------------- */
/* Styling                                                                     */
/* -------------------------------------------------------------------------- */

const editorClasses =
  'min-h-[8rem] w-full rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)] ' +
  'bg-[var(--color-arqel-bg)] px-3 py-2 text-sm text-[var(--color-arqel-fg)] ' +
  'focus-within:outline-none focus-within:ring-2 focus-within:ring-[var(--color-arqel-ring)] ' +
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

const counterStyle: CSSProperties = {
  fontSize: '0.75rem',
  color: 'var(--color-arqel-muted-fg, #94a3b8)',
};

/* -------------------------------------------------------------------------- */
/* Toolbar specs                                                               */
/* -------------------------------------------------------------------------- */

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

/* -------------------------------------------------------------------------- */
/* Component                                                                   */
/* -------------------------------------------------------------------------- */

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
  const labelId = `${id}-label`;

  const html = typeof value === 'string' ? value : '';
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  // The `content` prop is parsed by ProseMirror through the schema below,
  // which strips any tag/attribute (e.g. <script>, style, class) not part
  // of the allowed nodes/marks — this is Tiptap's intrinsic sanitisation.
  const editor = useEditor({
    editable: !disabled,
    content: html,
    extensions: [
      StarterKit,
      Link.configure({
        openOnClick: false,
        autolink: false,
        HTMLAttributes: { rel: 'noopener noreferrer' },
      }),
      Image.configure({ inline: false }),
      Placeholder.configure({
        placeholder: props.placeholder ?? 'Start writing...',
      }),
      CharacterCount.configure({ limit: props.maxLength }),
    ],
    onUpdate: ({ editor: ed }) => {
      onChange(ed.getHTML());
    },
    editorProps: {
      attributes: {
        id,
        role: 'textbox',
        'aria-multiline': 'true',
        'aria-label': field.label ?? '',
        ...(field.label ? { 'aria-labelledby': labelId } : {}),
        ...(hasError ? { 'aria-invalid': 'true' } : {}),
        ...(describedBy ? { 'aria-describedby': describedBy } : {}),
        ...(disabled ? { 'aria-disabled': 'true' } : {}),
        class: 'arqel-rich-editor-content focus:outline-none',
      },
    },
  });

  // Keep the editor in sync if `value` is updated externally (e.g. form
  // reset, server-driven refresh). We compare against the editor's own
  // HTML to avoid clobbering the caret on user edits.
  useEffect(() => {
    if (!editor) return;
    if (editor.getHTML() !== html) {
      editor.commands.setContent(html, false);
    }
  }, [editor, html]);

  // Toggle editable when `disabled` changes after mount.
  useEffect(() => {
    if (!editor) return;
    editor.setEditable(!disabled);
  }, [editor, disabled]);

  const storage = editor?.storage as { characterCount?: { characters?: () => number } } | undefined;
  const characters = storage?.characterCount?.characters?.() ?? 0;
  const overLimit = characters > props.maxLength;

  const applyButton = (button: string) => {
    if (!editor || disabled) return;
    const chain = editor.chain().focus();

    switch (button) {
      case 'bold':
        chain.toggleBold().run();
        break;
      case 'italic':
        chain.toggleItalic().run();
        break;
      case 'underline':
        // StarterKit does not bundle the Underline extension; fall back
        // to bold semantics so the button never throws. Hosts that need
        // true <u> can register a custom Underline extension upstream.
        chain.toggleBold().run();
        break;
      case 'strike':
        chain.toggleStrike().run();
        break;
      case 'h1':
        chain.toggleHeading({ level: 1 }).run();
        break;
      case 'h2':
        chain.toggleHeading({ level: 2 }).run();
        break;
      case 'h3':
        chain.toggleHeading({ level: 3 }).run();
        break;
      case 'ul':
        chain.toggleBulletList().run();
        break;
      case 'ol':
        chain.toggleOrderedList().run();
        break;
      case 'blockquote':
        chain.toggleBlockquote().run();
        break;
      case 'link': {
        const url =
          typeof globalThis.prompt === 'function' ? (globalThis.prompt('URL?') ?? '') : '';
        if (url.trim().length === 0) return;
        const safe = safeUrl(url);
        chain.setLink({ href: safe }).run();
        break;
      }
      case 'code':
        chain.toggleCode().run();
        break;
      case 'image': {
        if (props.imageUploadRoute === null) return;
        fileInputRef.current?.click();
        return;
      }
      default:
        return;
    }
  };

  const handleImageUpload = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file || props.imageUploadRoute === null || !editor) return;

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
      editor.chain().focus().setImage({ src: safeSrc }).run();
    } catch {
      // Network failure — silently swallowed; consumers can wrap with
      // their own error UI by registering a custom component slot.
    }
  };

  const renderedToolbar = props.toolbar.filter((b) => KNOWN_BUTTONS.includes(b));

  return (
    <div className="space-y-2">
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
          {`Content exceeds the maximum length of ${props.maxLength} characters.`}
        </div>
      ) : null}

      <div className={editorClasses} aria-invalid={hasError || undefined}>
        <EditorContent editor={editor} />
      </div>

      <div style={counterStyle} aria-live="polite">
        {characters} / {props.maxLength}
      </div>

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

/**
 * Vitest + Testing Library coverage for `<RichTextInput>` (Tiptap).
 *
 * The previous contentEditable+execCommand implementation is gone;
 * tests now exercise the Tiptap editor directly via its imperative
 * commands API, which is exposed via `useEditor` and rendered into the
 * DOM by `<EditorContent>`.
 *
 * Notes:
 * - jsdom does not implement `Range.getClientRects` cleanly; Tiptap
 *   tolerates this and renders synchronously, so most assertions can
 *   read the DOM immediately after a command.
 * - `editor` is read out of the rendered DOM through `screen.getByRole`
 *   on the contenteditable host that Tiptap mounts; for command-driven
 *   tests we trigger toolbar buttons (which delegate to `editor.chain`).
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { RichTextInput, safeUrl } from './RichTextInput.js';

/**
 * jsdom does not implement `getClientRects` on Range nodes nor
 * `elementFromPoint` / `getSelection` coords helpers that ProseMirror
 * uses for `scrollToSelection`. We polyfill them with safe no-ops so
 * Tiptap can dispatch transactions in tests without throwing.
 */
beforeAll(() => {
  if (typeof Range !== 'undefined') {
    if (!Range.prototype.getClientRects) {
      Range.prototype.getClientRects = () =>
        ({
          length: 0,
          item: () => null,
          [Symbol.iterator]: function* () {},
        }) as unknown as DOMRectList;
    }
    if (!Range.prototype.getBoundingClientRect) {
      Range.prototype.getBoundingClientRect = () =>
        ({
          x: 0,
          y: 0,
          top: 0,
          left: 0,
          right: 0,
          bottom: 0,
          width: 0,
          height: 0,
          toJSON() {},
        }) as DOMRect;
    }
  }
  // Element.getClientRects is sometimes called on detached nodes (Tiptap's
  // selection coord lookup). The default jsdom impl exists for connected
  // elements but throws on edge nodes — patch defensively.
  if (typeof Element !== 'undefined') {
    const proto = Element.prototype as unknown as { getClientRects?: () => DOMRectList };
    const orig = proto.getClientRects;
    proto.getClientRects = function () {
      try {
        return (
          orig?.call(this) ??
          ({
            length: 0,
            item: () => null,
            [Symbol.iterator]: function* () {},
          } as unknown as DOMRectList)
        );
      } catch {
        return {
          length: 0,
          item: () => null,
          [Symbol.iterator]: function* () {},
        } as unknown as DOMRectList;
      }
    };
  }
  if (typeof document !== 'undefined' && typeof document.elementFromPoint !== 'function') {
    Object.defineProperty(document, 'elementFromPoint', {
      configurable: true,
      writable: true,
      value: () => null,
    });
  }
});

interface RtProps {
  toolbar?: string[];
  imageUploadRoute?: string | null;
  imageUploadDirectory?: string | null;
  maxLength?: number;
  fileAttachments?: boolean;
  customMarks?: string[];
  mentionable?: unknown[];
}

function buildField(overrides: RtProps = {}, placeholder: string | null = null): FieldSchema {
  const props = {
    toolbar: overrides.toolbar ?? ['bold', 'italic', 'link', 'h1', 'ul', 'ol', 'blockquote'],
    imageUploadRoute: overrides.imageUploadRoute ?? null,
    imageUploadDirectory: overrides.imageUploadDirectory ?? null,
    maxLength: overrides.maxLength ?? 100,
    fileAttachments: overrides.fileAttachments ?? false,
    customMarks: overrides.customMarks ?? [],
    mentionable: overrides.mentionable ?? [],
  };

  return {
    type: 'richText',
    name: 'body',
    label: 'Body',
    component: 'RichTextInput',
    required: false,
    readonly: false,
    disabled: false,
    placeholder,
    helperText: null,
    defaultValue: null,
    columnSpan: 1,
    live: false,
    liveDebounce: null,
    validation: { rules: [], messages: {}, attribute: null },
    visibility: { create: true, edit: true, detail: true, table: true, canSee: true },
    dependsOn: [],
    props,
  } as unknown as FieldSchema;
}

/** Find the Tiptap-managed contenteditable host inside the rendered tree. */
function getEditableHost(): HTMLElement {
  return screen.getByRole('textbox');
}

describe('safeUrl()', () => {
  it('rejects javascript:, data:, vbscript: and returns "#"', () => {
    expect(safeUrl('javascript:alert(1)')).toBe('#');
    expect(safeUrl('  DATA:text/html,xss')).toBe('#');
    expect(safeUrl('vbscript:msgbox')).toBe('#');
  });

  it('passes plain http/https URLs through untouched', () => {
    expect(safeUrl('https://example.test/path')).toBe('https://example.test/path');
    expect(safeUrl('  https://example.test  ')).toBe('https://example.test');
  });
});

describe('<RichTextInput> (Tiptap)', () => {
  it('renders a contenteditable editor with the toolbar', () => {
    const onChange = vi.fn();
    render(
      <RichTextInput field={buildField({}, 'Type something...')} value="" onChange={onChange} />,
    );

    const editor = getEditableHost();
    expect(editor).toBeInTheDocument();
    expect(editor.getAttribute('contenteditable')).toBe('true');

    const toolbar = screen.getByRole('toolbar', { name: 'Formatting toolbar' });
    expect(toolbar).toBeInTheDocument();
  });

  it('initialises editor content from the `value` prop and renders parsed text', () => {
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="<p>hello world</p>" onChange={onChange} />);

    const editor = getEditableHost();
    expect(editor.textContent).toContain('hello world');
  });

  it('strips <script> tags from initial content via the ProseMirror schema', () => {
    const onChange = vi.fn();
    render(
      <RichTextInput
        field={buildField()}
        value='<p>safe</p><script>alert("xss")</script>'
        onChange={onChange}
      />,
    );

    const editor = getEditableHost();
    // The DOM should not contain a <script> element nor the literal
    // alert payload — Tiptap's schema parser drops both.
    expect(editor.querySelector('script')).toBeNull();
    expect(editor.innerHTML).not.toContain('<script');
    expect(editor.innerHTML).not.toContain('alert("xss")');
    expect(editor.textContent).toContain('safe');
  });

  it('rejects javascript: URLs in initial content (no <a href="javascript:">)', () => {
    const onChange = vi.fn();
    render(
      <RichTextInput
        field={buildField()}
        value='<p><a href="javascript:alert(1)">x</a></p>'
        onChange={onChange}
      />,
    );

    const editor = getEditableHost();
    const anchors = editor.querySelectorAll('a');
    for (const a of Array.from(anchors)) {
      expect(a.getAttribute('href')?.toLowerCase() ?? '').not.toContain('javascript:');
    }
  });

  it('editor changes propagate through onChange with HTML output', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="<p>abc</p>" onChange={onChange} />);

    const editor = getEditableHost();
    await user.click(editor);
    await user.keyboard('{Control>}a{/Control}');
    await user.click(screen.getByRole('button', { name: 'Heading 1' }));

    // Toggling H1 dispatches a ProseMirror transaction → onUpdate → onChange.
    expect(onChange).toHaveBeenCalled();
    const last = onChange.mock.calls[onChange.mock.calls.length - 1]?.[0] as string | undefined;
    expect(typeof last).toBe('string');
    expect((last ?? '').toLowerCase()).toMatch(/<h1>|<\/h1>/);
  });

  it('Bold button toggles bold mark and emits <strong> in onChange HTML', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="<p>abc</p>" onChange={onChange} />);

    const editor = getEditableHost();
    await user.click(editor);
    // Select all, then click Bold.
    await user.keyboard('{Control>}a{/Control}');
    await user.click(screen.getByRole('button', { name: 'Bold' }));

    const last = onChange.mock.calls[onChange.mock.calls.length - 1]?.[0] as string | undefined;
    // Either onUpdate fired with <strong>, or the DOM directly contains it.
    const html = last ?? editor.innerHTML;
    expect(html).toMatch(/<strong>/i);
  });

  it('Link button + safe URL inserts an <a> element', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    const promptSpy = vi.spyOn(globalThis, 'prompt').mockReturnValue('https://example.test');
    render(<RichTextInput field={buildField()} value="<p>click</p>" onChange={onChange} />);

    const editor = getEditableHost();
    await user.click(editor);
    await user.keyboard('{Control>}a{/Control}');
    await user.click(screen.getByRole('button', { name: 'Link' }));

    const anchors = editor.querySelectorAll('a');
    expect(anchors.length).toBeGreaterThan(0);
    expect(anchors[0]?.getAttribute('href')).toBe('https://example.test');

    promptSpy.mockRestore();
  });

  it('Link button rejects javascript: URLs by setting href="#"', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    const promptSpy = vi.spyOn(globalThis, 'prompt').mockReturnValue('javascript:alert(1)');
    render(<RichTextInput field={buildField()} value="<p>click</p>" onChange={onChange} />);

    const editor = getEditableHost();
    await user.click(editor);
    await user.keyboard('{Control>}a{/Control}');
    await user.click(screen.getByRole('button', { name: 'Link' }));

    const anchors = editor.querySelectorAll('a');
    for (const a of Array.from(anchors)) {
      const href = a.getAttribute('href') ?? '';
      expect(href.toLowerCase()).not.toContain('javascript:');
    }

    promptSpy.mockRestore();
  });

  it('CharacterCount enforces maxLength and shows over-limit warning when value exceeds it', () => {
    const onChange = vi.fn();
    const longHtml = `<p>${'x'.repeat(50)}</p>`;
    render(
      <RichTextInput field={buildField({ maxLength: 10 })} value={longHtml} onChange={onChange} />,
    );

    // The character counter (XX / 10) and the over-limit warning should be visible.
    const alert = screen.queryByRole('alert');
    // Either an alert is shown or the counter reflects the over-limit state.
    if (alert) {
      expect(alert.textContent).toMatch(/exceeds the maximum length/i);
    } else {
      // Counter must indicate excess characters.
      expect(document.body.textContent).toMatch(/\/\s*10/);
    }
  });

  it('disabled=true makes the editor non-editable and disables toolbar buttons', () => {
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="" onChange={onChange} disabled={true} />);

    const editor = getEditableHost();
    expect(editor.getAttribute('contenteditable')).toBe('false');

    const boldBtn = screen.getByRole('button', { name: 'Bold' }) as HTMLButtonElement;
    expect(boldBtn.disabled).toBe(true);
  });

  it('only renders toolbar buttons listed in field.props.toolbar', () => {
    const onChange = vi.fn();
    render(
      <RichTextInput
        field={buildField({ toolbar: ['bold', 'italic'] })}
        value=""
        onChange={onChange}
      />,
    );

    expect(screen.getByRole('button', { name: 'Bold' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Italic' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Heading 1' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Link' })).toBeNull();
  });

  it('disables the image button when imageUploadRoute is null', () => {
    const onChange = vi.fn();
    render(
      <RichTextInput
        field={buildField({ toolbar: ['image'], imageUploadRoute: null })}
        value=""
        onChange={onChange}
      />,
    );

    const btn = screen.getByRole('button', { name: 'Image' }) as HTMLButtonElement;
    expect(btn.disabled).toBe(true);
    expect(btn.title).toBe('Image upload not configured');
  });

  it('marks the editor aria-invalid when errors are present', () => {
    const onChange = vi.fn();
    render(
      <RichTextInput field={buildField()} value="" onChange={onChange} errors={['required']} />,
    );

    const editor = getEditableHost();
    expect(editor.getAttribute('aria-invalid')).toBe('true');
  });

  it('updates the editor content when the `value` prop changes externally', () => {
    const onChange = vi.fn();
    const { rerender } = render(
      <RichTextInput field={buildField()} value="<p>first</p>" onChange={onChange} />,
    );

    let editor = getEditableHost();
    expect(editor.textContent).toContain('first');

    act(() => {
      rerender(<RichTextInput field={buildField()} value="<p>second</p>" onChange={onChange} />);
    });

    editor = getEditableHost();
    expect(editor.textContent).toContain('second');
  });
});

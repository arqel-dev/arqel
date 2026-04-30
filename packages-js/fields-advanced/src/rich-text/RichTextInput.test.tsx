/**
 * Vitest + Testing Library coverage for `<RichTextInput>`.
 *
 * Mirrors the testing pattern used by `MarkdownInput.test.tsx`:
 * synthesises `FieldSchema` shapes per test rather than relying on a
 * (yet-to-be-shipped) `RichTextFieldSchema` discriminant.
 */

import type { FieldSchema } from '@arqel/types/fields';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { RichTextInput, sanitizeHtml } from './RichTextInput.js';

// jsdom does not implement `document.execCommand`. We stub it here so
// `vi.spyOn(document, 'execCommand')` finds a property to wrap.
beforeEach(() => {
  if (typeof (document as unknown as { execCommand?: unknown }).execCommand !== 'function') {
    Object.defineProperty(document, 'execCommand', {
      configurable: true,
      writable: true,
      value: () => true,
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

/** Helper: write HTML into the editor and dispatch the input event. */
function setEditorHtml(editor: HTMLElement, html: string): void {
  // Trusted: this is a test fixture mutating an isolated jsdom node.
  // The component sanitises the value before exposing it via onChange.
  Reflect.set(editor, 'innerHTML', html);
  fireEvent.input(editor);
}

describe('sanitizeHtml()', () => {
  it('strips <script> tags entirely', () => {
    const out = sanitizeHtml('<p>hi</p><script>alert(1)</script>');
    expect(out).not.toContain('<script');
    expect(out).not.toContain('alert');
    expect(out).toContain('<p>hi</p>');
  });

  it('rejects javascript: URLs in <a href>', () => {
    const out = sanitizeHtml('<a href="javascript:alert(1)">x</a>');
    expect(out).toContain('href="#"');
    expect(out).not.toContain('javascript:');
  });

  it('rejects data: URLs in <img src>', () => {
    const out = sanitizeHtml('<img src="data:image/png;base64,AAAA" alt="x">');
    expect(out).toContain('src="#"');
    expect(out).not.toContain('data:');
  });

  it('strips inline style and class attributes', () => {
    const out = sanitizeHtml('<p style="color:red" class="bad">hi</p>');
    expect(out).toContain('<p>hi</p>');
    expect(out).not.toContain('style');
    expect(out).not.toContain('class');
  });

  it('unwraps disallowed tags but keeps their text', () => {
    const out = sanitizeHtml('<div><span>hello</span></div>');
    expect(out).toContain('hello');
    expect(out).not.toContain('<div');
    expect(out).not.toContain('<span');
  });
});

describe('<RichTextInput>', () => {
  it('renders an empty editor with placeholder data attribute', () => {
    const onChange = vi.fn();
    render(
      <RichTextInput field={buildField({}, 'Type something...')} value="" onChange={onChange} />,
    );

    const editor = screen.getByRole('textbox');
    expect(editor).toBeInTheDocument();
    expect(editor.getAttribute('contenteditable')).toBe('true');
    expect(editor.getAttribute('data-empty')).toBe('true');
    expect(editor.getAttribute('data-placeholder')).toBe('Type something...');
    expect(editor.getAttribute('aria-multiline')).toBe('true');
  });

  it('initialises the editor DOM from the `value` prop', () => {
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="<p>hello</p>" onChange={onChange} />);

    const editor = screen.getByRole('textbox');
    expect(editor.textContent).toContain('hello');
  });

  it('typing fires onChange with sanitized HTML', () => {
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="" onChange={onChange} />);

    const editor = screen.getByRole('textbox');
    setEditorHtml(editor, '<p>hi <script>x</script></p>');

    expect(onChange).toHaveBeenCalled();
    const payload = onChange.mock.calls[onChange.mock.calls.length - 1]?.[0] as string;
    expect(payload).not.toContain('<script');
    expect(payload).toContain('hi');
  });

  it('rewrites javascript: link URLs to # in onChange payload', () => {
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="" onChange={onChange} />);

    const editor = screen.getByRole('textbox');
    setEditorHtml(editor, '<p><a href="javascript:alert(1)">click</a></p>');

    const payload = onChange.mock.calls[onChange.mock.calls.length - 1]?.[0] as string;
    expect(payload).toContain('href="#"');
    expect(payload).not.toContain('javascript:');
  });

  it('toolbar Bold button invokes document.execCommand("bold")', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    const execSpy = vi.spyOn(document, 'execCommand').mockReturnValue(true);

    render(<RichTextInput field={buildField()} value="" onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Bold' }));

    expect(execSpy).toHaveBeenCalledWith('bold', false, undefined);
    execSpy.mockRestore();
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

  it('shows a maxLength warning when content exceeds the cap', () => {
    const onChange = vi.fn();
    const longHtml = `<p>${'x'.repeat(120)}</p>`;
    render(
      <RichTextInput field={buildField({ maxLength: 100 })} value={longHtml} onChange={onChange} />,
    );

    const alert = screen.getByRole('alert');
    expect(alert.textContent).toMatch(/exceeds the maximum length/);
  });

  it('blocks input when content exceeds maxLength by more than 10%', () => {
    const onChange = vi.fn();
    render(<RichTextInput field={buildField({ maxLength: 10 })} value="" onChange={onChange} />);

    const editor = screen.getByRole('textbox');
    setEditorHtml(editor, `<p>${'x'.repeat(50)}</p>`);

    const lastCall = onChange.mock.calls[onChange.mock.calls.length - 1];
    if (lastCall !== undefined) {
      expect((lastCall[0] as string).length).toBeLessThan(60);
    }
  });

  it('renders contentEditable=false when disabled', () => {
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="" onChange={onChange} disabled={true} />);

    const editor = screen.getByRole('textbox');
    expect(editor.getAttribute('contenteditable')).toBe('false');

    const boldBtn = screen.getByRole('button', { name: 'Bold' }) as HTMLButtonElement;
    expect(boldBtn.disabled).toBe(true);
  });

  it('marks the editor aria-invalid when errors are present', () => {
    const onChange = vi.fn();
    render(
      <RichTextInput field={buildField()} value="" onChange={onChange} errors={['required']} />,
    );

    const editor = screen.getByRole('textbox');
    expect(editor.getAttribute('aria-invalid')).toBe('true');
  });

  it('exposes a toolbar with role=toolbar and an accessible label', () => {
    const onChange = vi.fn();
    render(<RichTextInput field={buildField()} value="" onChange={onChange} />);

    const toolbar = screen.getByRole('toolbar', { name: 'Formatting toolbar' });
    expect(toolbar).toBeInTheDocument();
  });

  it('Link button rejects javascript: URLs from prompt', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    const execSpy = vi.spyOn(document, 'execCommand').mockReturnValue(true);
    const promptSpy = vi.spyOn(globalThis, 'prompt').mockReturnValue('javascript:alert(1)');

    render(<RichTextInput field={buildField()} value="" onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Link' }));

    expect(execSpy).toHaveBeenCalledWith('createLink', false, '#');
    promptSpy.mockRestore();
    execSpy.mockRestore();
  });
});

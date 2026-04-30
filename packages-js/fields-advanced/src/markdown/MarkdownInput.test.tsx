/**
 * Vitest + Testing Library coverage for `<MarkdownInput>`.
 *
 * Composes synthetic `FieldSchema` shapes per test rather than relying
 * on a (yet to be shipped) `MarkdownFieldSchema` discriminant from
 * `@arqel/types`. The component itself is type-safe via its defensive
 * `readProps()` narrowing.
 */

import type { FieldSchema } from '@arqel/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { MarkdownInput } from './MarkdownInput.js';

interface MdProps {
  preview?: boolean;
  previewMode?: 'side-by-side' | 'tab' | 'popup';
  toolbar?: boolean;
  rows?: number;
  fullscreen?: boolean;
  syncScroll?: boolean;
}

function buildField(overrides: MdProps = {}): FieldSchema {
  const props: Required<MdProps> = {
    preview: overrides.preview ?? true,
    previewMode: overrides.previewMode ?? 'side-by-side',
    toolbar: overrides.toolbar ?? true,
    rows: overrides.rows ?? 10,
    fullscreen: overrides.fullscreen ?? true,
    syncScroll: overrides.syncScroll ?? true,
  };

  return {
    type: 'markdown',
    name: 'body',
    label: 'Body',
    component: 'MarkdownInput',
    required: false,
    readonly: false,
    disabled: false,
    placeholder: null,
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

describe('<MarkdownInput>', () => {
  it('renders an empty textarea + preview pane in side-by-side mode', () => {
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField()} value="" onChange={onChange} />);

    const textarea = screen.getByLabelText('Body') as HTMLTextAreaElement;
    expect(textarea).toBeInTheDocument();
    expect(textarea.value).toBe('');
    expect(screen.getByRole('region', { name: 'Markdown preview' })).toBeInTheDocument();
  });

  it('forwards textarea typing to onChange', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField()} value="" onChange={onChange} />);

    const textarea = screen.getByLabelText('Body');
    await user.type(textarea, 'hi');

    // userEvent.type fires per-character, so the last call carries the
    // last character only — assert it was called with the full payload
    // somewhere in the call sequence.
    const calls = onChange.mock.calls.map((c) => c[0]);
    expect(calls).toContain('h');
    expect(calls).toContain('i');
  });

  it('reflects the current value in the preview pane', () => {
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField()} value="hello world" onChange={onChange} />);

    const preview = screen.getByRole('region', { name: 'Markdown preview' });
    expect(preview.innerHTML).toContain('<p>hello world</p>');
  });

  it('renders `# Heading` as <h1>', () => {
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField()} value="# Title" onChange={onChange} />);

    const preview = screen.getByRole('region', { name: 'Markdown preview' });
    expect(preview.innerHTML).toContain('<h1>Title</h1>');
  });

  it('renders `**bold**` as <strong>', () => {
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField()} value="**hi** there" onChange={onChange} />);

    const preview = screen.getByRole('region', { name: 'Markdown preview' });
    expect(preview.innerHTML).toContain('<strong>hi</strong>');
  });

  it('rejects javascript: URLs and renders the link as plain text', () => {
    const onChange = vi.fn();
    render(
      <MarkdownInput
        field={buildField()}
        value="[click](javascript:alert(1))"
        onChange={onChange}
      />,
    );

    const preview = screen.getByRole('region', { name: 'Markdown preview' });
    // The rejected URL must NOT become a clickable anchor — it remains
    // as escaped literal markdown so the user can see (and edit) it.
    expect(preview.querySelector('a')).toBeNull();
    expect(preview.innerHTML).not.toContain('href');
    expect(preview.textContent).toContain('[click]');
  });

  it('escapes raw HTML so script tags do not execute', () => {
    const onChange = vi.fn();
    render(
      <MarkdownInput field={buildField()} value="<script>alert(1)</script>" onChange={onChange} />,
    );

    const preview = screen.getByRole('region', { name: 'Markdown preview' });
    expect(preview.querySelector('script')).toBeNull();
    expect(preview.innerHTML).toContain('&lt;script&gt;');
  });

  it('toolbar bold button wraps selected text in **', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField()} value="hello world" onChange={onChange} />);

    const textarea = screen.getByLabelText('Body') as HTMLTextAreaElement;
    textarea.focus();
    textarea.setSelectionRange(0, 5); // "hello"

    await user.click(screen.getByRole('button', { name: 'Bold' }));

    expect(onChange).toHaveBeenLastCalledWith('**hello** world');
  });

  it('previewMode="tab": clicking Preview tab hides editor and shows preview', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <MarkdownInput field={buildField({ previewMode: 'tab' })} value="hi" onChange={onChange} />,
    );

    // Editor visible by default.
    expect(screen.getByLabelText('Body')).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Markdown preview' })).toBeNull();

    await user.click(screen.getByRole('tab', { name: 'Preview' }));

    expect(screen.queryByLabelText('Body')).toBeNull();
    expect(screen.getByRole('region', { name: 'Markdown preview' })).toBeInTheDocument();
  });

  it('disables the textarea when the `disabled` prop is set', () => {
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField()} value="" onChange={onChange} disabled={true} />);

    const textarea = screen.getByLabelText('Body') as HTMLTextAreaElement;
    expect(textarea.disabled).toBe(true);
  });

  it('marks the textarea as aria-invalid when errors are present', () => {
    const onChange = vi.fn();
    render(
      <MarkdownInput field={buildField()} value="" onChange={onChange} errors={['required']} />,
    );

    const textarea = screen.getByLabelText('Body');
    expect(textarea).toHaveAttribute('aria-invalid', 'true');
  });

  it('clamps `rows` below the minimum of 3', () => {
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField({ rows: 1 })} value="" onChange={onChange} />);

    const textarea = screen.getByLabelText('Body') as HTMLTextAreaElement;
    expect(textarea.rows).toBe(3);
  });
});

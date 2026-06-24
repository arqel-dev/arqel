/**
 * Vitest + Testing Library coverage for `<MarkdownInput>`.
 *
 * Composes synthetic `FieldSchema` shapes per test rather than relying
 * on a (yet to be shipped) `MarkdownFieldSchema` discriminant from
 * `@arqel-dev/types`. The component itself is type-safe via its defensive
 * `readProps()` narrowing.
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { usePage } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { MarkdownInput } from './MarkdownInput.js';

// MarkdownInput now localizes its control aria-labels via
// `useArqelTranslations()`, which reads Inertia's `usePage()`. Stub an
// empty-props page so the hook falls back to the English literals these
// assertions expect (no `i18n.translations` dictionary present).
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: vi.fn(() => ({ props: {} })) };
});

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

  // --- i18n: preview button + fullscreen toggle visible text & aria-label ---

  it('falls back to English literals for the preview/fullscreen controls', () => {
    const onChange = vi.fn();
    render(
      <MarkdownInput
        field={buildField({ previewMode: 'popup', fullscreen: true })}
        value=""
        onChange={onChange}
      />,
    );
    // Visible preview button text (was a hardcoded literal before the fix).
    expect(screen.getByRole('button', { name: 'Open preview' })).toHaveTextContent('Preview');
    // Fullscreen toggle: English aria-label + visible "Full" short text.
    const fullscreen = screen.getByRole('button', { name: 'Enter fullscreen' });
    expect(fullscreen).toHaveTextContent('Full');
  });

  it('routes the link/list toolbar glyphs through t() (visible == aria)', () => {
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField()} value="" onChange={onChange} />);
    // Visible face must equal the (translated) aria-label so they stay in sync.
    expect(screen.getByRole('button', { name: 'Link' })).toHaveTextContent('Link');
    expect(screen.getByRole('button', { name: 'List' })).toHaveTextContent('List');
  });

  it('routes the edit/preview tab labels through t()', () => {
    const onChange = vi.fn();
    render(<MarkdownInput field={buildField({ previewMode: 'tab' })} value="" onChange={onChange} />);
    expect(screen.getByRole('tab', { name: 'Edit' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Preview' })).toBeInTheDocument();
  });

  it('routes the popup close button through t()', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <MarkdownInput field={buildField({ previewMode: 'popup' })} value="" onChange={onChange} />,
    );
    await user.click(screen.getByRole('button', { name: 'Open preview' }));
    expect(screen.getByRole('button', { name: 'Close' })).toHaveTextContent('Close');
  });
});

describe('<MarkdownInput> localization', () => {
  afterEach(() => {
    vi.mocked(usePage).mockReturnValue({ props: {} } as unknown as ReturnType<typeof usePage>);
  });

  it('routes the preview button + fullscreen toggle through t()', () => {
    vi.mocked(usePage).mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          translations: {
            arqel: {
              fields_advanced: {
                markdown_preview_open: 'Abrir pré-visualização',
                markdown_preview_label: 'Pré-visualização',
                markdown_enter_fullscreen: 'Entrar em tela cheia',
                markdown_fullscreen_short: 'Tela cheia',
              },
            },
          },
        },
      },
    } as unknown as ReturnType<typeof usePage>);

    const onChange = vi.fn();
    render(
      <MarkdownInput
        field={buildField({ previewMode: 'popup', fullscreen: true })}
        value=""
        onChange={onChange}
      />,
    );

    expect(screen.getByRole('button', { name: 'Abrir pré-visualização' })).toHaveTextContent(
      'Pré-visualização',
    );
    expect(screen.getByRole('button', { name: 'Entrar em tela cheia' })).toHaveTextContent(
      'Tela cheia',
    );
  });

  it('translates the edit/preview tabs and popup close button', async () => {
    vi.mocked(usePage).mockReturnValue({
      props: {
        i18n: {
          locale: 'pt_BR',
          translations: {
            arqel: {
              fields_advanced: {
                markdown_tab_edit: 'Editar',
                markdown_tab_preview: 'Pré-visualizar',
                markdown_close: 'Fechar',
                markdown_preview_open: 'Abrir pré-visualização',
              },
            },
          },
        },
      },
    } as unknown as ReturnType<typeof usePage>);

    const user = userEvent.setup();
    const onChange = vi.fn();
    const { rerender } = render(
      <MarkdownInput field={buildField({ previewMode: 'tab' })} value="" onChange={onChange} />,
    );
    expect(screen.getByRole('tab', { name: 'Editar' })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Pré-visualizar' })).toBeInTheDocument();

    rerender(
      <MarkdownInput field={buildField({ previewMode: 'popup' })} value="" onChange={onChange} />,
    );
    await user.click(screen.getByRole('button', { name: 'Abrir pré-visualização' }));
    expect(screen.getByRole('button', { name: 'Fechar' })).toHaveTextContent('Fechar');
  });
});

/**
 * Vitest + Testing Library coverage for `<CodeInput>` (FIELDS-ADV-012,
 * scoped to the textarea-based fallback). Mirrors the synthetic-schema
 * pattern used by `MarkdownInput.test.tsx`.
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

// Mock the dynamic `import('shiki')` so tests don't pull the real
// (~1MB) WASM-backed Shiki bundle into the jsdom env. The mock returns
// a faux highlighter that emits a recognisable colored span — enough
// to assert the overlay layer wires up correctly.
vi.mock('shiki', () => {
  const codeToHtml = (code: string, opts: { lang: string; theme: string }) =>
    `<pre class="shiki" style="background-color:#0d1117" data-shiki-lang="${opts.lang}" data-shiki-theme="${opts.theme}"><code><span style="color:#ff7b72">${code}</span></code></pre>`;
  return {
    createHighlighter: vi.fn(async () => ({ codeToHtml })),
  };
});

import { CodeInput } from './CodeInput.js';

interface CodeProps {
  language?: string;
  theme?: string | null;
  lineNumbers?: boolean;
  wordWrap?: boolean;
  tabSize?: number;
  readonly?: boolean;
  minHeight?: number | null;
}

function buildField(overrides: CodeProps = {}): FieldSchema {
  const props = {
    language: overrides.language ?? 'typescript',
    theme: overrides.theme ?? null,
    lineNumbers: overrides.lineNumbers ?? true,
    wordWrap: overrides.wordWrap ?? false,
    tabSize: overrides.tabSize ?? 2,
    readonly: overrides.readonly ?? false,
    minHeight: overrides.minHeight ?? null,
  };

  return {
    type: 'code',
    name: 'snippet',
    label: 'Snippet',
    component: 'CodeInput',
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

describe('<CodeInput>', () => {
  it('renders an empty textarea labelled by the field label', () => {
    const onChange = vi.fn();
    render(<CodeInput field={buildField()} value="" onChange={onChange} />);

    const textarea = screen.getByLabelText('Snippet') as HTMLTextAreaElement;
    expect(textarea).toBeInTheDocument();
    expect(textarea.value).toBe('');
  });

  it('forwards typing to onChange', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<CodeInput field={buildField()} value="" onChange={onChange} />);

    const textarea = screen.getByLabelText('Snippet');
    await user.type(textarea, 'ab');

    const calls = onChange.mock.calls.map((c) => c[0]);
    expect(calls).toContain('a');
    expect(calls).toContain('b');
  });

  it('Tab key inserts spaces (not a tab character) at the cursor', () => {
    const onChange = vi.fn();
    render(<CodeInput field={buildField({ tabSize: 4 })} value="hi" onChange={onChange} />);

    const textarea = screen.getByLabelText('Snippet') as HTMLTextAreaElement;
    textarea.focus();
    textarea.setSelectionRange(2, 2);

    fireEvent.keyDown(textarea, { key: 'Tab' });

    expect(onChange).toHaveBeenCalledWith('hi    ');
    // Must not contain a literal tab character.
    const inserted = onChange.mock.calls[0]?.[0] as string;
    expect(inserted.includes('\t')).toBe(false);
  });

  it('Shift+Tab removes leading indent at the cursor line', () => {
    const onChange = vi.fn();
    render(<CodeInput field={buildField({ tabSize: 2 })} value="    hello" onChange={onChange} />);

    const textarea = screen.getByLabelText('Snippet') as HTMLTextAreaElement;
    textarea.focus();
    textarea.setSelectionRange(4, 4);

    fireEvent.keyDown(textarea, { key: 'Tab', shiftKey: true });

    // Removes 2 leading spaces (one tabSize worth).
    expect(onChange).toHaveBeenCalledWith('  hello');
  });

  it('renders the line-numbers gutter when lineNumbers=true', () => {
    const onChange = vi.fn();
    render(
      <CodeInput field={buildField({ lineNumbers: true })} value={'a\nb\nc'} onChange={onChange} />,
    );

    const gutter = screen.getByTestId('code-gutter');
    expect(gutter).toBeInTheDocument();
    expect(gutter.textContent).toContain('1');
    expect(gutter.textContent).toContain('2');
    expect(gutter.textContent).toContain('3');
  });

  it('hides the gutter when lineNumbers=false', () => {
    const onChange = vi.fn();
    render(<CodeInput field={buildField({ lineNumbers: false })} value="a" onChange={onChange} />);

    expect(screen.queryByTestId('code-gutter')).toBeNull();
  });

  it('disabled=true disables the textarea', () => {
    const onChange = vi.fn();
    render(<CodeInput field={buildField()} value="" onChange={onChange} disabled={true} />);

    const textarea = screen.getByLabelText('Snippet') as HTMLTextAreaElement;
    expect(textarea.disabled).toBe(true);
  });

  it('readonly prop marks the textarea as readOnly', () => {
    const onChange = vi.fn();
    render(<CodeInput field={buildField({ readonly: true })} value="x" onChange={onChange} />);

    const textarea = screen.getByLabelText('Snippet') as HTMLTextAreaElement;
    expect(textarea.readOnly).toBe(true);
  });

  it('renders a language badge mapped to a friendly display name', () => {
    const onChange = vi.fn();
    render(<CodeInput field={buildField({ language: 'php' })} value="" onChange={onChange} />);

    const badge = screen.getByTestId('code-language-badge');
    expect(badge.textContent).toBe('PHP');
  });

  it('marks the textarea aria-invalid when errors are present', () => {
    const onChange = vi.fn();
    render(<CodeInput field={buildField()} value="" onChange={onChange} errors={['required']} />);

    const textarea = screen.getByLabelText('Snippet');
    expect(textarea).toHaveAttribute('aria-invalid', 'true');
  });

  it('toggles fullscreen via the toolbar button', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<CodeInput field={buildField()} value="" onChange={onChange} />);

    const button = screen.getByRole('button', { name: 'Enter fullscreen' });
    await user.click(button);

    expect(screen.getByRole('button', { name: 'Exit fullscreen' })).toBeInTheDocument();
  });

  /* ------------------------------------------------------------------------ */
  /* Shiki overlay layer                                                      */
  /* ------------------------------------------------------------------------ */

  it('renders the Shiki overlay with colored tokens for known languages', async () => {
    const onChange = vi.fn();
    render(
      <CodeInput
        field={buildField({ language: 'javascript' })}
        value="const x = 1"
        onChange={onChange}
      />,
    );

    const overlay = screen.getByTestId('code-overlay');
    // Wait for the lazy `import('shiki')` + createHighlighter to resolve.
    await waitFor(() => {
      expect(overlay.innerHTML).toContain('<span style="color:');
    });
    expect(overlay.innerHTML).toContain('data-shiki-lang="javascript"');
  });

  it('skips Shiki for plaintext language (no colored tokens, just escaped text)', async () => {
    const onChange = vi.fn();
    render(
      <CodeInput
        field={buildField({ language: 'plaintext' })}
        value="hello <world>"
        onChange={onChange}
      />,
    );

    const overlay = screen.getByTestId('code-overlay');
    // No async work — should be the static fallback already.
    expect(overlay.innerHTML).toContain('shiki-fallback');
    // The angle brackets must be escaped, not rendered as a tag.
    expect(overlay.innerHTML).toContain('hello &lt;world&gt;');
    // No colored token spans from the Shiki mock.
    expect(overlay.innerHTML).not.toContain('data-shiki-lang');

    // Give any pending microtasks a chance to settle and confirm we
    // still didn't switch to a Shiki-rendered output.
    await new Promise((r) => setTimeout(r, 10));
    expect(overlay.innerHTML).not.toContain('data-shiki-lang');
  });

  it('falls back to plaintext rendering for unknown languages without throwing', async () => {
    const onChange = vi.fn();
    expect(() => {
      render(
        <CodeInput
          field={buildField({ language: 'unknown-fake-lang' })}
          value="abc"
          onChange={onChange}
        />,
      );
    }).not.toThrow();

    const overlay = screen.getByTestId('code-overlay');
    expect(overlay.innerHTML).toContain('shiki-fallback');
    expect(overlay.innerHTML).toContain('abc');

    // Confirm we never invoked Shiki for this unknown language.
    await new Promise((r) => setTimeout(r, 10));
    expect(overlay.innerHTML).not.toContain('data-shiki-lang');
  });
});

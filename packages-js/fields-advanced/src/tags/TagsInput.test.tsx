import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { fireEvent, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { TagsInput } from './TagsInput.js';

interface TagsTestProps {
  suggestions?: string[];
  creatable?: boolean;
  maxTags?: number | null;
  separator?: string;
  unique?: boolean;
}

function makeField(props: TagsTestProps = {}): FieldRendererProps['field'] {
  return {
    type: 'text',
    name: 'tags',
    label: 'Tags',
    component: 'TagsInput',
    required: false,
    readonly: false,
    disabled: false,
    placeholder: 'Add a tag…',
    helperText: null,
    defaultValue: null,
    columnSpan: 1,
    live: false,
    liveDebounce: null,
    validation: { rules: [], messages: {}, attribute: null },
    visibility: { create: true, edit: true, detail: true, table: true, canSee: true },
    dependsOn: [],
    props: {
      suggestions: props.suggestions ?? [],
      creatable: props.creatable ?? true,
      maxTags: props.maxTags ?? null,
      separator: props.separator ?? ',',
      unique: props.unique ?? true,
    },
  } as unknown as FieldRendererProps['field'];
}

function renderTags(
  initialValue: string[] | null,
  fieldProps?: TagsTestProps,
  extras?: Partial<FieldRendererProps>,
) {
  const onChange = vi.fn();
  const utils = render(
    <TagsInput
      field={makeField(fieldProps)}
      value={initialValue}
      onChange={onChange}
      inputId="t-input"
      {...extras}
    />,
  );
  return { onChange, ...utils };
}

describe('TagsInput', () => {
  it('renders empty (no chips) and shows the input', () => {
    renderTags(null);
    expect(screen.getByRole('combobox')).toBeInTheDocument();
    expect(screen.queryAllByLabelText(/Remove tag/)).toHaveLength(0);
  });

  it('hydrates initial value into chips', () => {
    renderTags(['php', 'laravel']);
    expect(screen.getByText('php')).toBeInTheDocument();
    expect(screen.getByText('laravel')).toBeInTheDocument();
  });

  it('commits a tag on Enter', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags([]);
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'react{Enter}');
    expect(onChange).toHaveBeenLastCalledWith(['react']);
  });

  it("commits on the configured separator (default ',')", async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags([]);
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'inertia,');
    expect(onChange).toHaveBeenLastCalledWith(['inertia']);
  });

  it('commits on a custom separator', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags([], { separator: ';' });
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'foo;');
    expect(onChange).toHaveBeenLastCalledWith(['foo']);
  });

  it('removes the last tag on Backspace when input is empty', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags(['a', 'b']);
    const input = screen.getByLabelText('Tags');
    input.focus();
    await user.keyboard('{Backspace}');
    expect(onChange).toHaveBeenLastCalledWith(['a']);
  });

  it('skips duplicates when unique=true (case-insensitive)', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags(['React'], { unique: true });
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'react{Enter}');
    expect(onChange).not.toHaveBeenCalled();
  });

  it('allows duplicates when unique=false', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags(['react'], { unique: false });
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'react{Enter}');
    expect(onChange).toHaveBeenLastCalledWith(['react', 'react']);
  });

  it('enforces maxTags', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags(['a', 'b'], { maxTags: 2 });
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'c{Enter}');
    expect(onChange).not.toHaveBeenCalled();
  });

  it('rejects unknown tags when creatable=false', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags([], {
      creatable: false,
      suggestions: ['php', 'laravel'],
    });
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'rust{Enter}');
    expect(onChange).not.toHaveBeenCalled();
    await user.clear(input);
    await user.type(input, 'php{Enter}');
    expect(onChange).toHaveBeenLastCalledWith(['php']);
  });

  it('filters suggestions case-insensitively as user types', async () => {
    const user = userEvent.setup();
    renderTags([], { suggestions: ['PHP', 'Python', 'Ruby'] });
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'p');
    const listbox = screen.getByRole('listbox');
    const options = within(listbox).getAllByRole('option');
    expect(options.map((o) => o.textContent)).toEqual(['PHP', 'Python']);
  });

  it('commits a suggestion when clicked', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags([], { suggestions: ['laravel', 'lumen'] });
    const input = screen.getByLabelText('Tags');
    await user.type(input, 'la');
    const option = screen.getByRole('option', { name: 'laravel' });
    fireEvent.mouseDown(option);
    fireEvent.click(option);
    expect(onChange).toHaveBeenLastCalledWith(['laravel']);
  });

  it('removes a single tag via its remove button', async () => {
    const user = userEvent.setup();
    const { onChange } = renderTags(['a', 'b', 'c']);
    await user.click(screen.getByLabelText('Remove tag b'));
    expect(onChange).toHaveBeenLastCalledWith(['a', 'c']);
  });

  it('hides remove buttons when disabled', () => {
    renderTags(['x'], {}, { disabled: true });
    expect(screen.queryByLabelText('Remove tag x')).not.toBeInTheDocument();
  });

  it('sets aria-invalid when errors are present', () => {
    renderTags([], {}, { errors: ['oops'] });
    const input = screen.getByLabelText('Tags');
    expect(input).toHaveAttribute('aria-invalid', 'true');
  });
});

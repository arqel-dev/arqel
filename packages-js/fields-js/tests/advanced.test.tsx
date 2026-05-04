import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { DateInput } from '../src/date/index.js';
import { FileInput } from '../src/file/index.js';
import { HasManyReadonly } from '../src/relationship/index.js';
import { MultiSelectInput, RadioGroup, SelectInput } from '../src/select/index.js';

const baseField = {
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
};

const select: FieldSchema = {
  ...baseField,
  type: 'select',
  name: 'role',
  label: 'Role',
  component: 'SelectInput',
  props: {
    options: [
      { value: 'admin', label: 'Admin' },
      { value: 'user', label: 'User' },
    ],
  },
};

const multi: FieldSchema = {
  ...baseField,
  type: 'multiSelect',
  name: 'tags',
  label: 'Tags',
  component: 'MultiSelectInput',
  props: {
    multiple: true,
    options: [
      { value: 'a', label: 'Alpha' },
      { value: 'b', label: 'Beta' },
    ],
  },
};

const radio: FieldSchema = {
  ...baseField,
  type: 'radio',
  name: 'plan',
  label: 'Plan',
  component: 'RadioGroup',
  props: {
    options: [
      { value: 'free', label: 'Free' },
      { value: 'pro', label: 'Pro' },
    ],
    inline: false,
  },
};

const date: FieldSchema = {
  ...baseField,
  type: 'date',
  name: 'birthday',
  label: 'Birthday',
  component: 'DateInput',
  props: { format: 'yyyy-MM-dd', displayFormat: 'yyyy-MM-dd' },
};

const file: FieldSchema = {
  ...baseField,
  type: 'file',
  name: 'avatar',
  label: 'Avatar',
  component: 'FileInput',
  props: { disk: 'public' },
};

const hasMany: FieldSchema = {
  ...baseField,
  type: 'hasMany',
  name: 'posts',
  label: 'Posts',
  component: 'HasManyReadonly',
  props: { relatedResource: 'posts', relationship: 'posts' },
};

describe('SelectInput', () => {
  it('emits value change', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<SelectInput field={select} value={null} onChange={onChange} />);
    await user.selectOptions(screen.getByRole('combobox'), 'admin');
    expect(onChange).toHaveBeenCalledWith('admin');
  });
});

describe('MultiSelectInput', () => {
  it('renders chips for selected values and removes via button', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<MultiSelectInput field={multi} value={['a']} onChange={onChange} />);
    expect(screen.getByRole('button', { name: 'Remove Alpha' })).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Remove Alpha' }));
    expect(onChange).toHaveBeenCalledWith([]);
  });
});

describe('RadioGroup', () => {
  it('selects an option', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<RadioGroup field={radio} value={null} onChange={onChange} />);
    await user.click(screen.getByLabelText('Pro'));
    expect(onChange).toHaveBeenCalledWith('pro');
  });
});

describe('DateInput', () => {
  it('emits ISO date strings', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    const { container } = render(<DateInput field={date} value="" onChange={onChange} />);
    const input = container.querySelector('input[type="date"]') as HTMLInputElement;
    await user.type(input, '2026-01-15');
    expect(onChange).toHaveBeenCalled();
  });
});

describe('FileInput', () => {
  it('shows placeholder when empty and filename when set', () => {
    const onChange = vi.fn();
    const { rerender } = render(<FileInput field={file} value={null} onChange={onChange} />);
    expect(screen.getByText(/drag a file/i)).toBeInTheDocument();

    const f = new File(['data'], 'avatar.png', { type: 'image/png' });
    rerender(<FileInput field={file} value={f} onChange={onChange} />);
    expect(screen.getByText('avatar.png')).toBeInTheDocument();
  });
});

describe('HasManyReadonly', () => {
  it('renders empty message when no records', () => {
    render(<HasManyReadonly field={hasMany} value={[]} onChange={() => {}} />);
    expect(screen.getByText(/no posts linked/i)).toBeInTheDocument();
  });

  it('renders related list', () => {
    render(
      <HasManyReadonly
        field={hasMany}
        value={[
          { id: 1, label: 'Hello' },
          { id: 2, label: 'World' },
        ]}
        onChange={() => {}}
      />,
    );
    expect(screen.getByText('Hello')).toBeInTheDocument();
    expect(screen.getByText('World')).toBeInTheDocument();
  });
});

/**
 * Vitest + Testing Library coverage for `<KeyValueInput>`.
 *
 * The schema/props plumbing is exercised by composing a synthetic
 * `FieldSchema` shape per test rather than relying on the (yet to be
 * shipped) `KeyValueFieldSchema` discriminant from `@arqel-dev/types`.
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { KeyValueInput } from './KeyValueInput.js';

interface KvProps {
  keyLabel?: string;
  valueLabel?: string;
  keyPlaceholder?: string;
  valuePlaceholder?: string;
  editableKeys?: boolean;
  addable?: boolean;
  deletable?: boolean;
  reorderable?: boolean;
  asObject?: boolean;
}

function buildField(overrides: KvProps = {}): FieldSchema {
  const props: Required<KvProps> = {
    keyLabel: overrides.keyLabel ?? 'Key',
    valueLabel: overrides.valueLabel ?? 'Value',
    keyPlaceholder: overrides.keyPlaceholder ?? '',
    valuePlaceholder: overrides.valuePlaceholder ?? '',
    editableKeys: overrides.editableKeys ?? true,
    addable: overrides.addable ?? true,
    deletable: overrides.deletable ?? true,
    reorderable: overrides.reorderable ?? false,
    asObject: overrides.asObject ?? false,
  };

  // Cast through `unknown` because `keyValue` is not yet a declared
  // discriminant in `@arqel-dev/types` — the component itself is type-safe
  // via its defensive `readProps()` narrowing.
  return {
    type: 'keyValue',
    name: 'metadata',
    label: 'Metadata',
    component: 'KeyValueInput',
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

describe('<KeyValueInput>', () => {
  it('renders the legend, headers and add button when value is empty', () => {
    const onChange = vi.fn();
    render(<KeyValueInput field={buildField()} value={[]} onChange={onChange} />);

    expect(screen.getByText('Metadata')).toBeInTheDocument();
    expect(screen.getByText('Key')).toBeInTheDocument();
    expect(screen.getByText('Value')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /add/i })).toBeInTheDocument();
    expect(screen.queryByLabelText('Key 1')).toBeNull();
  });

  it('adds a new row when the add button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<KeyValueInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: /add/i }));

    expect(screen.getByLabelText('Key 1')).toBeInTheDocument();
    expect(screen.getByLabelText('Value 1')).toBeInTheDocument();
    expect(onChange).toHaveBeenLastCalledWith([{ key: '', value: '' }]);
  });

  it('emits an array of {key, value} as the user types (default shape)', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<KeyValueInput field={buildField()} value={[]} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: /add/i }));
    await user.type(screen.getByLabelText('Key 1'), 'foo');
    await user.type(screen.getByLabelText('Value 1'), 'bar');

    expect(onChange).toHaveBeenLastCalledWith([{ key: 'foo', value: 'bar' }]);
  });

  it('renders hydrated rows and disables the key input when editableKeys=false', () => {
    const onChange = vi.fn();
    render(
      <KeyValueInput
        field={buildField({ editableKeys: false })}
        value={[{ key: 'env', value: 'prod' }]}
        onChange={onChange}
      />,
    );

    const keyInput = screen.getByLabelText('Key 1') as HTMLInputElement;
    expect(keyInput).toHaveValue('env');
    expect(keyInput.readOnly || keyInput.disabled).toBe(true);

    // Value column remains editable.
    const valueInput = screen.getByLabelText('Value 1') as HTMLInputElement;
    expect(valueInput.readOnly).toBe(false);
    expect(valueInput.disabled).toBe(false);
  });

  it('hides the remove button when deletable=false', () => {
    const onChange = vi.fn();
    render(
      <KeyValueInput
        field={buildField({ deletable: false })}
        value={[{ key: 'a', value: '1' }]}
        onChange={onChange}
      />,
    );

    expect(screen.queryByRole('button', { name: /remove row/i })).toBeNull();
  });

  it('hides the add button when addable=false', () => {
    const onChange = vi.fn();
    render(<KeyValueInput field={buildField({ addable: false })} value={[]} onChange={onChange} />);

    expect(screen.queryByRole('button', { name: /add/i })).toBeNull();
  });

  it('emits an associative object when asObject=true', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<KeyValueInput field={buildField({ asObject: true })} value={{}} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: /add/i }));
    await user.type(screen.getByLabelText('Key 1'), 'foo');
    await user.type(screen.getByLabelText('Value 1'), 'bar');

    expect(onChange).toHaveBeenLastCalledWith({ foo: 'bar' });
  });

  it('removes a row when the × button is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <KeyValueInput
        field={buildField()}
        value={[
          { key: 'a', value: '1' },
          { key: 'b', value: '2' },
        ]}
        onChange={onChange}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Remove row 1' }));

    expect(onChange).toHaveBeenLastCalledWith([{ key: 'b', value: '2' }]);
  });

  it('marks inputs as aria-invalid when errors are present', () => {
    const onChange = vi.fn();
    render(
      <KeyValueInput
        field={buildField()}
        value={[{ key: 'a', value: '1' }]}
        onChange={onChange}
        errors={['invalid map']}
      />,
    );

    expect(screen.getByLabelText('Key 1')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByLabelText('Value 1')).toHaveAttribute('aria-invalid', 'true');
  });
});

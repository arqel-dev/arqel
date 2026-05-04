import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Checkbox, Toggle } from '../src/boolean/index.js';
import { CurrencyInput, NumberInput } from '../src/number/index.js';
import { EmailInput, PasswordInput, TextareaInput, TextInput } from '../src/text/index.js';

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

const text: FieldSchema = {
  ...baseField,
  type: 'text',
  name: 'name',
  label: 'Name',
  component: 'TextInput',
  props: {},
};

const email: FieldSchema = {
  ...baseField,
  type: 'email',
  name: 'email',
  label: 'Email',
  component: 'EmailInput',
  props: {},
};

const password: FieldSchema = {
  ...baseField,
  type: 'password',
  name: 'pwd',
  label: 'Password',
  component: 'PasswordInput',
  props: {},
};

const textarea: FieldSchema = {
  ...baseField,
  type: 'textarea',
  name: 'bio',
  label: 'Bio',
  component: 'TextareaInput',
  props: {},
};

const number: FieldSchema = {
  ...baseField,
  type: 'number',
  name: 'count',
  label: 'Count',
  component: 'NumberInput',
  props: { step: 1 },
};

const currency: FieldSchema = {
  ...baseField,
  type: 'currency',
  name: 'price',
  label: 'Price',
  component: 'CurrencyInput',
  props: {
    prefix: 'R$ ',
    thousandsSeparator: '.',
    decimalSeparator: ',',
    decimals: 2,
  },
};

const boolean: FieldSchema = {
  ...baseField,
  type: 'boolean',
  name: 'active',
  label: 'Active',
  component: 'Checkbox',
  props: {},
};

const toggle: FieldSchema = {
  ...baseField,
  type: 'toggle',
  name: 'subscribe',
  label: 'Subscribe',
  component: 'Toggle',
  props: { onLabel: 'On', offLabel: 'Off' },
};

describe('TextInput', () => {
  it('emits value on change and sets aria-invalid on error', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    const { rerender } = render(<TextInput field={text} value="" onChange={onChange} />);
    await user.type(screen.getByRole('textbox'), 'a');
    expect(onChange).toHaveBeenCalledWith('a');

    rerender(<TextInput field={text} value="" onChange={onChange} errors={['Required']} />);
    expect(screen.getByRole('textbox')).toHaveAttribute('aria-invalid', 'true');
  });
});

describe('EmailInput', () => {
  it('uses type=email', () => {
    render(<EmailInput field={email} value="" onChange={() => {}} />);
    const input = screen.getByRole('textbox') as HTMLInputElement;
    expect(input.type).toBe('email');
  });
});

describe('PasswordInput', () => {
  it('toggles password visibility', async () => {
    const user = userEvent.setup();
    render(<PasswordInput field={password} value="hunter2" onChange={() => {}} />);
    const reveal = screen.getByRole('button', { name: 'Show password' });
    await user.click(reveal);
    expect(screen.getByRole('button', { name: 'Hide password' })).toBeInTheDocument();
    expect(screen.getByDisplayValue('hunter2')).toHaveAttribute('type', 'text');
  });
});

describe('TextareaInput', () => {
  it('renders a textarea with default rows', () => {
    render(<TextareaInput field={textarea} value="hello" onChange={() => {}} />);
    const ta = screen.getByDisplayValue('hello') as HTMLTextAreaElement;
    expect(ta.tagName).toBe('TEXTAREA');
    expect(ta.rows).toBe(4);
  });
});

describe('NumberInput', () => {
  it('emits null on empty input', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<NumberInput field={number} value={5} onChange={onChange} />);
    await user.clear(screen.getByRole('spinbutton'));
    expect(onChange).toHaveBeenLastCalledWith(null);
  });

  it('increments via stepper', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<NumberInput field={number} value={2} onChange={onChange} />);
    await user.click(screen.getByRole('button', { name: 'Increment' }));
    expect(onChange).toHaveBeenLastCalledWith(3);
  });
});

describe('CurrencyInput', () => {
  it('formats on blur and shows raw on focus', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<CurrencyInput field={currency} value={1234.5} onChange={onChange} />);
    const input = screen.getByDisplayValue('R$ 1.234,50') as HTMLInputElement;

    await user.click(input);
    expect(input.value).toBe('1234.5');
  });
});

describe('Checkbox', () => {
  it('toggles checked state', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<Checkbox field={boolean} value={false} onChange={onChange} />);
    await user.click(screen.getByRole('checkbox'));
    expect(onChange).toHaveBeenCalledWith(true);
  });
});

describe('Toggle', () => {
  it('renders role=switch and flips on click', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    const { rerender } = render(<Toggle field={toggle} value={false} onChange={onChange} />);
    const sw = screen.getByRole('switch');
    expect(sw).toHaveAttribute('aria-checked', 'false');
    expect(screen.getByText('Off')).toBeInTheDocument();

    await user.click(sw);
    expect(onChange).toHaveBeenCalledWith(true);

    rerender(<Toggle field={toggle} value={true} onChange={onChange} />);
    expect(screen.getByRole('switch')).toHaveAttribute('aria-checked', 'true');
    expect(screen.getByText('On')).toBeInTheDocument();
  });
});

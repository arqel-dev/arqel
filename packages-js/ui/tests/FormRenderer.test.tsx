import type { FieldSchema } from '@arqel/types/fields';
import type { FormSchema } from '@arqel/types/forms';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { clearFieldRegistry, registerField } from '../src/form/FieldRegistry.js';
import { FormRenderer } from '../src/form/FormRenderer.js';

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

const nameField: FieldSchema = {
  ...baseField,
  type: 'text',
  name: 'name',
  label: 'Name',
  component: 'TextInput',
  props: {},
};

const emailField: FieldSchema = {
  ...baseField,
  type: 'email',
  name: 'email',
  label: 'Email',
  component: 'EmailInput',
  required: true,
  props: {},
};

const activeField: FieldSchema = {
  ...baseField,
  type: 'boolean',
  name: 'active',
  label: 'Active',
  component: 'BooleanInput',
  props: {},
};

const fields = [nameField, emailField, activeField];

const flatSchema: FormSchema = {
  schema: [
    { kind: 'field', name: 'name', type: 'text' },
    { kind: 'field', name: 'email', type: 'email' },
    { kind: 'field', name: 'active', type: 'boolean' },
  ],
  columns: 1,
  model: null,
  inline: false,
  disabled: false,
};

afterEach(() => clearFieldRegistry());

describe('FormRenderer', () => {
  it('renders fields by name and emits onChange', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <FormRenderer
        schema={flatSchema}
        fields={fields}
        values={{ name: '', email: '', active: false }}
        onChange={onChange}
      />,
    );

    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument();
    await user.type(screen.getByLabelText(/^Name/), 'a');
    expect(onChange).toHaveBeenCalledWith('name', 'a');
  });

  it('renders required marker', () => {
    render(
      <FormRenderer
        schema={flatSchema}
        fields={fields}
        values={{ email: '' }}
        onChange={() => {}}
      />,
    );
    // The required indicator is an aria-hidden asterisk
    const emailLabel = screen.getByText('Email').closest('label');
    expect(emailLabel?.textContent).toContain('*');
  });

  it('shows inline error message with role=alert', () => {
    render(
      <FormRenderer
        schema={flatSchema}
        fields={fields}
        values={{ email: '' }}
        errors={{ email: ['Required'] }}
        onChange={() => {}}
      />,
    );
    expect(screen.getByRole('alert')).toHaveTextContent('Required');
  });

  it('honours schema.disabled by disabling all fields', () => {
    render(
      <FormRenderer
        schema={{ ...flatSchema, disabled: true }}
        fields={fields}
        values={{ name: '', email: '', active: false }}
        onChange={() => {}}
      />,
    );
    expect(screen.getByLabelText(/^Name/)).toBeDisabled();
    expect(screen.getByLabelText(/^Email/)).toBeDisabled();
  });

  it('uses a registered custom field component', () => {
    registerField('TextInput', ({ field }) => (
      <span data-testid="custom" data-field={field.name}>
        custom
      </span>
    ));

    render(<FormRenderer schema={flatSchema} fields={fields} values={{}} onChange={() => {}} />);
    expect(screen.getByTestId('custom')).toHaveAttribute('data-field', 'name');
  });

  it('renders Section heading and description', () => {
    const schema: FormSchema = {
      ...flatSchema,
      schema: [
        {
          kind: 'layout',
          type: 'section',
          component: 'Section',
          columnSpan: 1,
          props: {
            heading: 'Profile',
            description: 'Public-facing details',
            columns: 1,
          },
          schema: [{ kind: 'field', name: 'name', type: 'text' }],
        },
      ],
    };

    render(<FormRenderer schema={schema} fields={fields} values={{}} onChange={() => {}} />);
    expect(screen.getByRole('heading', { name: 'Profile' })).toBeInTheDocument();
    expect(screen.getByText('Public-facing details')).toBeInTheDocument();
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument();
  });

  it('renders Tabs and switches via Arrow keys', async () => {
    const user = userEvent.setup();
    const schema: FormSchema = {
      ...flatSchema,
      schema: [
        {
          kind: 'layout',
          type: 'tabs',
          component: 'Tabs',
          columnSpan: 1,
          props: { defaultTab: 'first', orientation: 'horizontal' },
          schema: [
            {
              kind: 'layout',
              type: 'tab',
              component: 'Tab',
              columnSpan: 1,
              props: { id: 'first', label: 'First' },
              schema: [{ kind: 'field', name: 'name', type: 'text' }],
            },
            {
              kind: 'layout',
              type: 'tab',
              component: 'Tab',
              columnSpan: 1,
              props: { id: 'second', label: 'Second' },
              schema: [{ kind: 'field', name: 'email', type: 'email' }],
            },
          ],
        },
      ],
    };

    render(<FormRenderer schema={schema} fields={fields} values={{}} onChange={() => {}} />);
    expect(screen.getByLabelText(/^Name/)).toBeInTheDocument();
    expect(screen.queryByLabelText(/^Email/)).toBeNull();

    const firstTab = screen.getByRole('tab', { name: 'First' });
    firstTab.focus();
    await user.keyboard('{ArrowRight}');
    expect(screen.getByLabelText(/^Email/)).toBeInTheDocument();
  });
});

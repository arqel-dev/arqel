/**
 * Vitest + Testing Library coverage for `<WizardInput>`.
 *
 * The schema/props plumbing is exercised by composing a synthetic
 * `FieldSchema` shape per test rather than relying on a (yet to be
 * shipped) `WizardFieldSchema` discriminant from `@arqel-dev/types`.
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { WizardInput } from './WizardInput.js';

interface SubField {
  name: string;
  type: string;
  label?: string;
  required?: boolean;
}

interface StepConfig {
  name: string;
  label: string;
  icon?: string | null;
  schema: SubField[];
}

interface WizardConfig {
  steps?: StepConfig[];
  persistInUrl?: boolean;
  skippable?: boolean;
}

const defaultSteps: StepConfig[] = [
  {
    name: 'profile',
    label: 'Profile',
    schema: [{ name: 'firstName', type: 'text', label: 'First Name', required: true }],
  },
  {
    name: 'contact',
    label: 'Contact',
    schema: [{ name: 'email', type: 'text', label: 'Email' }],
  },
  {
    name: 'review',
    label: 'Review',
    schema: [{ name: 'notes', type: 'textarea', label: 'Notes' }],
  },
];

function buildField(overrides: WizardConfig = {}): FieldSchema {
  const props = {
    steps: overrides.steps ?? defaultSteps,
    persistInUrl: overrides.persistInUrl ?? false,
    skippable: overrides.skippable ?? false,
  };

  return {
    type: 'wizard',
    name: 'signup',
    label: 'Signup',
    component: 'WizardInput',
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

describe('<WizardInput>', () => {
  beforeEach(() => {
    if (typeof window !== 'undefined' && window.history) {
      window.history.replaceState(null, '', window.location.pathname);
    }
  });

  afterEach(() => {
    if (typeof window !== 'undefined' && window.history) {
      window.history.replaceState(null, '', window.location.pathname);
    }
  });

  it('renders the title and all steps with the first step active', () => {
    const onChange = vi.fn();
    render(<WizardInput field={buildField()} value={{}} onChange={onChange} />);

    expect(screen.getByText('Signup')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Step 1: Profile' })).toHaveAttribute(
      'aria-current',
      'step',
    );
    expect(screen.getByLabelText(/First Name/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Next' })).toBeInTheDocument();
  });

  it('advances to step 2 when Next is clicked (skippable=true bypasses validation)', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<WizardInput field={buildField({ skippable: true })} value={{}} onChange={onChange} />);

    await user.click(screen.getByRole('button', { name: 'Next' }));

    expect(screen.getByRole('button', { name: 'Step 2: Contact' })).toHaveAttribute(
      'aria-current',
      'step',
    );
    expect(screen.getByLabelText('Email')).toBeInTheDocument();
  });

  it('returns to the previous step when Back is clicked', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(
      <WizardInput
        field={buildField({ skippable: true })}
        value={{ firstName: 'Ada' }}
        onChange={onChange}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Next' }));
    await user.click(screen.getByRole('button', { name: 'Back' }));

    expect(screen.getByRole('button', { name: 'Step 1: Profile' })).toHaveAttribute(
      'aria-current',
      'step',
    );
  });

  it('disables the Back button on the first step', () => {
    render(<WizardInput field={buildField()} value={{}} onChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Back' })).toBeDisabled();
  });

  it('shows a Submit button on the last step instead of Next', async () => {
    const user = userEvent.setup();
    render(
      <WizardInput
        field={buildField({ skippable: true })}
        value={{ firstName: 'Ada' }}
        onChange={vi.fn()}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Next' }));
    await user.click(screen.getByRole('button', { name: 'Next' }));

    expect(screen.queryByRole('button', { name: 'Next' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Submit' })).toBeInTheDocument();
  });

  it('exposes aria-current="step" only on the active progress header item', async () => {
    const user = userEvent.setup();
    render(<WizardInput field={buildField({ skippable: true })} value={{}} onChange={vi.fn()} />);
    const active = screen.getByRole('button', { name: 'Step 1: Profile' });
    expect(active).toHaveAttribute('aria-current', 'step');
    // Other reachable steps render as buttons too (skippable=true).
    const next = screen.getByRole('button', { name: 'Step 2: Contact' });
    expect(next).not.toHaveAttribute('aria-current');

    await user.click(next);
    expect(screen.getByRole('button', { name: 'Step 2: Contact' })).toHaveAttribute(
      'aria-current',
      'step',
    );
  });

  it('jumps to a clicked step in the header when skippable=true', async () => {
    const user = userEvent.setup();
    render(<WizardInput field={buildField({ skippable: true })} value={{}} onChange={vi.fn()} />);

    await user.click(screen.getByRole('button', { name: 'Step 3: Review' }));

    expect(screen.getByRole('button', { name: 'Step 3: Review' })).toHaveAttribute(
      'aria-current',
      'step',
    );
    expect(screen.getByLabelText('Notes')).toBeInTheDocument();
  });

  it('blocks Next when a required field is empty (skippable=false)', async () => {
    const user = userEvent.setup();
    render(<WizardInput field={buildField()} value={{}} onChange={vi.fn()} />);

    await user.click(screen.getByRole('button', { name: 'Next' }));

    expect(screen.getByRole('alert')).toHaveTextContent(/First Name is required/i);
    expect(screen.getByRole('button', { name: 'Step 1: Profile' })).toHaveAttribute(
      'aria-current',
      'step',
    );
  });

  it('skippable=true bypasses required-field validation and lets Next advance', async () => {
    const user = userEvent.setup();
    render(<WizardInput field={buildField({ skippable: true })} value={{}} onChange={vi.fn()} />);

    await user.click(screen.getByRole('button', { name: 'Next' }));

    expect(screen.queryByRole('alert')).toBeNull();
    expect(screen.getByRole('button', { name: 'Step 2: Contact' })).toHaveAttribute(
      'aria-current',
      'step',
    );
  });

  it('persistInUrl=true syncs the current step to window.location.hash', async () => {
    const user = userEvent.setup();
    render(
      <WizardInput
        field={buildField({ skippable: true, persistInUrl: true })}
        value={{}}
        onChange={vi.fn()}
      />,
    );

    expect(window.location.hash).toBe('#step-profile');

    await user.click(screen.getByRole('button', { name: 'Next' }));

    expect(window.location.hash).toBe('#step-contact');
  });

  it('dispatches a wizard:submit CustomEvent on the last step Submit', async () => {
    const user = userEvent.setup();
    const handler = vi.fn();
    window.addEventListener('wizard:submit', handler);

    render(
      <WizardInput
        field={buildField({ skippable: true })}
        value={{ firstName: 'Ada' }}
        onChange={vi.fn()}
      />,
    );

    await user.click(screen.getByRole('button', { name: 'Next' }));
    await user.click(screen.getByRole('button', { name: 'Next' }));
    await user.click(screen.getByRole('button', { name: 'Submit' }));

    expect(handler).toHaveBeenCalledTimes(1);
    window.removeEventListener('wizard:submit', handler);
  });

  it('writes sub-field changes back through onChange merging with existing value', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<WizardInput field={buildField()} value={{ existing: 'keep' }} onChange={onChange} />);

    await user.type(screen.getByLabelText(/First Name/), 'A');

    expect(onChange).toHaveBeenLastCalledWith({ existing: 'keep', firstName: 'A' });
  });
});

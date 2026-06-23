/**
 * i18n chrome/form regression suite (group g5).
 *
 * Covers the residual hardcoded English strings + missing a11y semantics in
 * TenantSwitcher, DashboardFilters (date-range), FormSection, FieldRenderer
 * (required marker) and the native inputs (aria-invalid). Each spec mocks
 * `usePage().props.i18n` with a pt-BR dictionary and asserts the visible text /
 * accessible name resolves from the shared dictionary; companion specs assert
 * the English literal still renders when no dictionary is present (the
 * accessible name stays stable).
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import type { TenantSummary } from '@arqel-dev/types/tenant';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FieldRenderer } from '../src/form/FieldRenderer.js';
import { FormSection } from '../src/form/FormSection.js';
import { NativeFieldInput } from '../src/form/nativeFields.js';
import { TenantSwitcher } from '../src/shell/TenantSwitcher.js';
import { type DashboardFilterPayload, DashboardFilters } from '../src/widgets/DashboardFilters.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

const dict = {
  arqel: {
    aria: { tenant_switch: 'Trocar de tenant (atual: :tenant)' },
    tenant: { unnamed: 'Inquilino :id' },
  },
  table: {
    filters: {
      all: 'Todos',
      date_from: ':label de',
      date_to: ':label até',
    },
  },
  form: {
    required: 'Obrigatório',
    section: { show: 'Exibir', hide: 'Ocultar' },
  },
};

function usePtBr() {
  pageMock.mockReturnValue({
    props: { i18n: { locale: 'pt_BR', available: ['pt_BR'], translations: dict } },
  });
}

afterEach(() => {
  pageMock.mockReset();
  pageMock.mockReturnValue({ props: {} });
});

describe('TenantSwitcher unnamed fallback i18n', () => {
  const unnamed = { id: 5 } as TenantSummary;
  const other = { id: 6, name: 'Globex' } as TenantSummary;

  it('localizes the visible + aria label for an unnamed tenant', () => {
    usePtBr();
    render(<TenantSwitcher current={unnamed} available={[unnamed, other]} />);
    const trigger = screen.getByTestId('tenant-switcher-trigger');
    expect(within(trigger).getByText('Inquilino 5')).toBeInTheDocument();
    expect(trigger).toHaveAttribute('aria-label', 'Trocar de tenant (atual: Inquilino 5)');
  });

  it('falls back to the English "Tenant :id" literal with no dictionary', () => {
    render(<TenantSwitcher current={unnamed} available={[unnamed, other]} />);
    const trigger = screen.getByTestId('tenant-switcher-trigger');
    expect(within(trigger).getByText('Tenant 5')).toBeInTheDocument();
    expect(trigger).toHaveAttribute('aria-label', 'Switch tenant (current: Tenant 5)');
  });
});

describe('DashboardFilters date-range aria i18n', () => {
  const filters: DashboardFilterPayload[] = [
    { name: 'period', type: 'date_range', label: 'Período' },
  ];

  it('localizes the from/to date input accessible names', () => {
    usePtBr();
    render(<DashboardFilters filters={filters} values={{}} onChange={() => {}} />);
    expect(screen.getByLabelText('Período de')).toBeInTheDocument();
    expect(screen.getByLabelText('Período até')).toBeInTheDocument();
  });

  it('falls back to the English "<label> from"/"<label> to" literals', () => {
    render(<DashboardFilters filters={filters} values={{}} onChange={() => {}} />);
    expect(screen.getByLabelText('Período from')).toBeInTheDocument();
    expect(screen.getByLabelText('Período to')).toBeInTheDocument();
  });
});

describe('FormSection collapse toggle i18n', () => {
  const config = {
    heading: 'Seção',
    collapsible: true,
    collapsed: false,
  } as never;

  it('localizes the Hide/Show toggle text', () => {
    usePtBr();
    render(
      <FormSection config={config}>
        <span>body</span>
      </FormSection>,
    );
    // open by default -> "Hide"
    const toggle = screen.getByRole('button');
    expect(toggle).toHaveTextContent('Ocultar');
    fireEvent.click(toggle);
    expect(screen.getByRole('button')).toHaveTextContent('Exibir');
  });

  it('falls back to English Hide/Show literals', () => {
    render(
      <FormSection config={config}>
        <span>body</span>
      </FormSection>,
    );
    expect(screen.getByRole('button')).toHaveTextContent('Hide');
  });
});

const requiredTextField: FieldSchema = {
  type: 'text',
  name: 'title',
  label: 'Título',
  required: true,
  props: {},
} as never;

describe('FieldRenderer required-marker a11y i18n', () => {
  it('renders a localized sr-only "required" alongside the visual asterisk', () => {
    usePtBr();
    render(<FieldRenderer field={requiredTextField} value="" onChange={() => {}} />);
    // The visual marker is aria-hidden; the localized word is the accessible text.
    expect(screen.getByText('*')).toHaveAttribute('aria-hidden', 'true');
    expect(screen.getByText('(Obrigatório)')).toBeInTheDocument();
  });

  it('falls back to the English "Required" sr-only text', () => {
    render(<FieldRenderer field={requiredTextField} value="" onChange={() => {}} />);
    expect(screen.getByText('(Required)')).toBeInTheDocument();
  });
});

describe('native inputs aria-invalid in error state', () => {
  it('sets aria-invalid on the input when the field has errors', () => {
    render(
      <FieldRenderer
        field={requiredTextField}
        value=""
        onChange={() => {}}
        errors={['Campo obrigatório']}
      />,
    );
    const input = screen.getByRole('textbox');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    // and it is associated with the localized error message via aria-describedby
    const describedBy = input.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    const errorEl = document.getElementById((describedBy ?? '').split(' ').pop() ?? '');
    expect(errorEl).toHaveTextContent('Campo obrigatório');
  });

  it('omits aria-invalid when there is no error', () => {
    render(<FieldRenderer field={requiredTextField} value="" onChange={() => {}} />);
    expect(screen.getByRole('textbox')).not.toHaveAttribute('aria-invalid');
  });

  it('sets aria-invalid on a native select via the invalid prop', () => {
    const selectField: FieldSchema = {
      type: 'select',
      name: 'status',
      label: 'Status',
      props: { options: { a: 'A' } },
    } as never;
    render(<NativeFieldInput field={selectField} value={null} onChange={() => {}} invalid />);
    expect(screen.getByRole('combobox')).toHaveAttribute('aria-invalid', 'true');
  });
});

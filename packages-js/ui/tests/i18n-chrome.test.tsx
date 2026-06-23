/**
 * i18n chrome regression suite (group g4).
 *
 * Each `@arqel-dev/ui` chrome surface below used to render a hardcoded English
 * literal. These specs mock `usePage().props.i18n` with a pt-BR dictionary and
 * assert the visible text / accessible name resolves from the shared
 * dictionary, then (implicitly, via the other test files' English-fallback
 * coverage) that the literal still renders when no dictionary is present.
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import type { FilterSchema } from '@arqel-dev/types/tables';
import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ActionFormModal } from '../src/action/ActionFormModal.js';
import { ActionMenu } from '../src/action/ActionMenu.js';
import { ConfirmDialog } from '../src/action/ConfirmDialog.js';
import { NativeFieldInput } from '../src/form/nativeFields.js';
import { CommandPalette } from '../src/palette/CommandPalette.js';
import { DataTable } from '../src/table/DataTable.js';
import { TableFilters } from '../src/table/TableFilters.js';
import { DashboardFilters } from '../src/widgets/DashboardFilters.js';
import { TableCard } from '../src/widgets/TableCard.js';
import { WidgetRenderer } from '../src/widgets/WidgetRenderer.js';

const { pageMock } = vi.hoisted(() => ({ pageMock: vi.fn(() => ({ props: {} })) }));
vi.mock('@inertiajs/react', () => ({ usePage: pageMock }));

const dict = {
  arqel: {
    actions: { menu: 'Ações' },
    messages: { type_to_confirm: 'Digite :value para confirmar' },
  },
  table: {
    loading: 'Carregando…',
    empty: 'Nenhum registro encontrado.',
    filters: { all: 'Todos', yes: 'Sim', no: 'Não' },
  },
  form: {
    cancel: 'Cancelar',
    unregistered_field: 'O campo ":name" não pôde ser renderizado: :detail.',
    placeholder: { search_relation: 'Pesquisar :resource…' },
  },
  widgets: {
    table: { see_all: 'Ver todos →' },
    unknown_type: 'Tipo de widget :type não registrado',
  },
  palette: { placeholder: 'Digite um comando…' },
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
  component: null,
};

describe('DataTable chrome', () => {
  const columns = [
    {
      type: 'text',
      name: 'name',
      label: 'Name',
      sortable: false,
      searchable: false,
      copyable: false,
      hidden: false,
      hiddenOnMobile: false,
      align: 'start',
      width: null,
      tooltip: null,
      props: {},
    },
  ] as const;

  it('translates the loading row', () => {
    usePtBr();
    render(<DataTable columns={[...columns]} records={[]} loading />);
    expect(screen.getAllByText('Carregando…').length).toBeGreaterThan(0);
  });

  it('translates the empty fallback', () => {
    usePtBr();
    render(<DataTable columns={[...columns]} records={[]} />);
    expect(screen.getAllByText('Nenhum registro encontrado.').length).toBeGreaterThan(0);
  });
});

describe('TableFilters ternary', () => {
  const filters: FilterSchema[] = [
    {
      type: 'ternary',
      name: 'active',
      label: 'Active',
      persist: false,
      default: null,
      props: {},
    },
  ];

  it('translates All/Yes/No options', () => {
    usePtBr();
    render(<TableFilters filters={filters} values={{}} onChange={() => {}} />);
    expect(screen.getByRole('option', { name: 'Todos' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Sim' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Não' })).toBeInTheDocument();
  });
});

describe('nativeFields', () => {
  it('translates the unregistered-field notice', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    usePtBr();
    const field = {
      ...baseField,
      type: 'stateTransition',
      name: 'transition',
      label: 'Transition',
      component: 'pkg/Custom',
      props: {},
    } as unknown as FieldSchema;
    render(<NativeFieldInput field={field} value={null} onChange={() => {}} />);
    expect(screen.getByRole('alert').textContent).toContain('O campo "transition" não pôde');
    warn.mockRestore();
  });

  it('translates the belongsTo search placeholder', () => {
    usePtBr();
    const field = {
      ...baseField,
      type: 'belongsTo',
      name: 'author',
      label: 'Author',
      props: { relatedResource: 'users' },
    } as unknown as FieldSchema;
    render(<NativeFieldInput field={field} value={null} onChange={() => {}} />);
    expect(screen.getByPlaceholderText('Pesquisar users…')).toBeInTheDocument();
  });
});

describe('ConfirmDialog', () => {
  it('translates the type-to-confirm prompt while keeping the value as code', () => {
    usePtBr();
    render(
      <ConfirmDialog
        open
        onOpenChange={() => {}}
        onConfirm={() => {}}
        config={{ requiresText: 'DELETE' }}
      />,
    );
    expect(screen.getByText(/Digite/)).toBeInTheDocument();
    expect(screen.getByText(/para confirmar/)).toBeInTheDocument();
    expect(screen.getByText('DELETE').tagName).toBe('CODE');
  });
});

describe('ActionFormModal', () => {
  it('translates the Cancel button', () => {
    usePtBr();
    render(
      <ActionFormModal
        open
        onOpenChange={() => {}}
        action={{ name: 'edit', label: 'Editar', form: [] } as never}
        fields={[]}
        onSubmit={() => {}}
      />,
    );
    expect(screen.getByRole('button', { name: 'Cancelar' })).toBeInTheDocument();
  });
});

describe('ActionMenu', () => {
  const actions = [1, 2, 3, 4].map((n) => ({
    name: `a${n}`,
    label: `Action ${n}`,
    color: null,
    disabled: false,
    requiresConfirmation: false,
    form: null,
  })) as never[];

  it('translates the menu trigger accessible name', () => {
    usePtBr();
    render(<ActionMenu actions={actions} onInvoke={() => {}} />);
    expect(screen.getAllByRole('button', { name: 'Ações' }).length).toBeGreaterThan(0);
  });
});

describe('DashboardFilters', () => {
  it('translates the select "All" option', () => {
    usePtBr();
    render(
      <DashboardFilters
        filters={[{ name: 'status', type: 'select', label: 'Status', options: { a: 'A' } }]}
        values={{}}
        onChange={() => {}}
      />,
    );
    expect(screen.getByRole('option', { name: 'Todos' })).toBeInTheDocument();
  });
});

describe('TableCard', () => {
  it('translates the "See all" link', () => {
    usePtBr();
    render(
      <TableCard
        widget={{
          name: 'recent',
          type: 'table',
          columns: [{ name: 'id', label: 'ID' }],
          records: [{ id: 1 }],
          limit: 5,
          seeAllUrl: '/admin/posts',
        }}
      />,
    );
    expect(screen.getByRole('link', { name: 'Ver todos →' })).toBeInTheDocument();
  });
});

describe('WidgetRenderer', () => {
  it('translates the unknown-type alert', () => {
    usePtBr();
    render(<WidgetRenderer widget={{ name: 'x', type: 'mystery' }} />);
    expect(screen.getByRole('alert').textContent).toBe('Tipo de widget mystery não registrado');
  });
});

describe('CommandPalette', () => {
  it('translates the combobox placeholder', () => {
    usePtBr();
    render(<CommandPalette />);
    // The palette mounts inside a closed <dialog>, so the combobox is hidden
    // from the a11y tree until opened — query it including hidden elements.
    expect(screen.getByRole('combobox', { hidden: true })).toHaveAttribute(
      'placeholder',
      'Digite um comando…',
    );
  });
});

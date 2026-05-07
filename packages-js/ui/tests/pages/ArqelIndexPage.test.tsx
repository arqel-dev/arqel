/**
 * Tests for ArqelIndexPage — the bridge between Inertia server props
 * and presentational <ResourceIndex>. Covers the 4 BUG-VAL fixes
 * shipped in v0.9.2.
 */

import type { ActionSchema } from '@arqel-dev/types/actions';
import type {
  PaginationMeta,
  RecordType,
  ResourceIndexProps,
  ResourceMeta,
} from '@arqel-dev/types/resources';
import type { ColumnSchema, FilterSchema, TableSort } from '@arqel-dev/types/tables';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ArqelIndexPage from '../../src/pages/ArqelIndexPage.js';

const routerGetSpy = vi.fn();
const routerVisitSpy = vi.fn();
const usePageMock = vi.fn();

vi.mock('@inertiajs/react', () => ({
  router: {
    get: (...args: unknown[]) => routerGetSpy(...args),
    visit: (...args: unknown[]) => routerVisitSpy(...args),
  },
  usePage: () => usePageMock(),
}));

interface MakePropsArgs {
  records?: Array<RecordType>;
  pagination?: PaginationMeta | null;
  filters?: FilterSchema[];
  columns?: ColumnSchema[];
  search?: string | null;
  sort?: TableSort;
  actions?: { row: ActionSchema[]; bulk: ActionSchema[]; toolbar: ActionSchema[] };
}

function makeResource(): ResourceMeta {
  return {
    class: 'App\\Arqel\\Resources\\PostResource',
    slug: 'posts',
    label: 'Post',
    pluralLabel: 'Posts',
    navigationIcon: null,
    navigationGroup: 'Content',
  };
}

function makeProps(overrides: MakePropsArgs = {}): ResourceIndexProps {
  return {
    resource: makeResource(),
    records: overrides.records ?? [],
    pagination: overrides.pagination ?? {
      currentPage: 1,
      perPage: 25,
      lastPage: 1,
      total: 0,
    },
    filters: overrides.filters ?? [],
    columns: overrides.columns ?? [],
    search: overrides.search ?? null,
    sort: overrides.sort ?? { column: null, direction: null },
    actions: overrides.actions ?? { row: [], bulk: [], toolbar: [] },
  };
}

beforeEach(() => {
  routerGetSpy.mockClear();
  routerVisitSpy.mockClear();
  usePageMock.mockReset();
  // Reset URL each test
  Object.defineProperty(window, 'location', {
    writable: true,
    value: { pathname: '/admin/posts', search: '' },
  });
});

afterEach(() => {
  vi.useRealTimers();
});

function makeTextColumn(name: string, label: string): ColumnSchema {
  return {
    type: 'text',
    name,
    label,
    sortable: true,
    searchable: false,
    copyable: false,
    hidden: false,
    hiddenOnMobile: false,
    align: 'start',
    width: null,
    tooltip: null,
    props: {},
  };
}

function makeAction(name: string, label: string, type: 'row' | 'bulk' = 'row'): ActionSchema {
  return {
    name,
    type,
    label,
    color: 'primary',
    variant: 'default',
    method: 'POST',
  };
}

function makeTextFilter(name: string, label: string): FilterSchema {
  return {
    type: 'text',
    name,
    label,
    persist: false,
    default: null,
    props: {},
  };
}

describe('ArqelIndexPage — pagination preserves perPage', () => {
  it('emits visit with both ?page and ?per_page when handlePageChange runs after handlePerPageChange', async () => {
    const props = makeProps({
      pagination: { currentPage: 1, perPage: 25, lastPage: 3, total: 25 },
    });
    usePageMock.mockReturnValue({ props, url: '/admin/posts' });

    const user = userEvent.setup();
    const { container } = render(<ArqelIndexPage />);

    const perPageSelect = container.querySelector('select') as HTMLSelectElement;
    expect(perPageSelect).toBeTruthy();
    await user.selectOptions(perPageSelect, '10');

    const nextBtn = screen.getByRole('button', { name: /next page/i });
    await user.click(nextBtn);

    expect(routerGetSpy).toHaveBeenCalled();
    const lastCall = routerGetSpy.mock.calls[routerGetSpy.mock.calls.length - 1];
    expect(lastCall).toBeDefined();
    const [, data] = lastCall as [string, Record<string, unknown>, unknown];

    expect(data['page']).toBe(2);
    expect(data['per_page']).toBe(10);
  });
});

describe('ArqelIndexPage — filter change preserves perPage', () => {
  it('emits visit including ?per_page when a filter changes after perPage was set', async () => {
    const props = makeProps({
      pagination: { currentPage: 1, perPage: 25, lastPage: 1, total: 5 },
      filters: [makeTextFilter('title', 'Title')],
    });
    usePageMock.mockReturnValue({ props, url: '/admin/posts' });

    const user = userEvent.setup();
    const { container } = render(<ArqelIndexPage />);

    await user.selectOptions(container.querySelector('select') as HTMLSelectElement, '10');

    const titleInput = screen.getByLabelText(/title/i) as HTMLInputElement;
    await user.type(titleInput, 'foo');

    const lastCall = routerGetSpy.mock.calls[routerGetSpy.mock.calls.length - 1];
    expect(lastCall).toBeDefined();
    const [, data] = lastCall as [string, Record<string, unknown>, unknown];

    expect(data['per_page']).toBe(10);
    expect(data['filter']).toMatchObject({ title: 'foo' });
  });
});

describe('ArqelIndexPage — search change preserves perPage', () => {
  it('emits debounced visit including ?per_page after search input', async () => {
    const props = makeProps({
      pagination: { currentPage: 1, perPage: 25, lastPage: 1, total: 5 },
    });
    usePageMock.mockReturnValue({ props, url: '/admin/posts' });

    const user = userEvent.setup();
    const { container } = render(<ArqelIndexPage />);

    await user.selectOptions(container.querySelector('select') as HTMLSelectElement, '10');

    const searchInput = screen.getByPlaceholderText(/search posts/i) as HTMLInputElement;
    fireEvent.change(searchInput, { target: { value: 'hello' } });

    await waitFor(() => {
      const calls = routerGetSpy.mock.calls;
      const last = calls[calls.length - 1];
      expect(last).toBeDefined();
      const [, data] = last as [string, Record<string, unknown>, unknown];
      expect(data['search']).toBe('hello');
    });

    const lastCall = routerGetSpy.mock.calls[routerGetSpy.mock.calls.length - 1];
    const [, data] = lastCall as [string, Record<string, unknown>, unknown];
    expect(data['per_page']).toBe(10);
    expect(data['search']).toBe('hello');
  });
});

describe('ArqelIndexPage — row actions render per row', () => {
  it('renders row action buttons when actions.row is non-empty', () => {
    const props = makeProps({
      columns: [makeTextColumn('title', 'Title')],
      records: [
        { id: 1, title: 'first' },
        { id: 2, title: 'second' },
      ],
      actions: {
        row: [makeAction('edit', 'Edit'), makeAction('delete', 'Delete')],
        bulk: [],
        toolbar: [],
      },
    });
    usePageMock.mockReturnValue({ props, url: '/admin/posts' });

    render(<ArqelIndexPage />);

    const editButtons = screen.getAllByRole('button', { name: /edit/i });
    expect(editButtons.length).toBeGreaterThanOrEqual(2);
  });

  it('renders no row action buttons when actions.row is empty', () => {
    const props = makeProps({
      columns: [makeTextColumn('title', 'Title')],
      records: [{ id: 1, title: 'first' }],
      actions: { row: [], bulk: [], toolbar: [] },
    });
    usePageMock.mockReturnValue({ props, url: '/admin/posts' });

    render(<ArqelIndexPage />);

    expect(screen.queryByRole('button', { name: /edit/i })).toBeNull();
  });
});

describe('ArqelIndexPage — bulk actions render after selection', () => {
  it('shows bulk action button only after a row is selected', async () => {
    const props = makeProps({
      columns: [makeTextColumn('title', 'Title')],
      records: [
        { id: 1, title: 'first' },
        { id: 2, title: 'second' },
      ],
      actions: {
        row: [],
        bulk: [makeAction('deleteBulk', 'Delete selected', 'bulk')],
        toolbar: [],
      },
    });
    usePageMock.mockReturnValue({ props, url: '/admin/posts' });

    const user = userEvent.setup();
    render(<ArqelIndexPage />);

    expect(screen.queryByRole('button', { name: /delete selected/i })).toBeNull();

    const checkboxes = screen.getAllByRole('checkbox', { name: /select row/i });
    expect(checkboxes.length).toBeGreaterThanOrEqual(1);
    const firstCheckbox = checkboxes[0];
    if (!firstCheckbox) throw new Error('expected at least one row checkbox');
    await user.click(firstCheckbox);

    const bulkBtn = await screen.findByRole('button', { name: /delete selected/i });
    expect(bulkBtn).toBeInTheDocument();
  });
});

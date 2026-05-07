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
import { render, screen } from '@testing-library/react';
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

/**
 * Default Inertia page for `arqel::index`.
 *
 * Pulls every prop the server emits via `InertiaDataBuilder::
 * buildIndexData` / `buildTableIndexData` and forwards them to
 * `<ResourceIndex>`. Apps can override per-resource by registering
 * their own page component at `Pages/Arqel/{Slug}/Index.tsx` (the
 * lookup falls through to user pages first inside `createArqelApp`).
 *
 * Wires the standard table interactions — search / filter / sort /
 * pagination — into Inertia partial `router.get` visits so the
 * server can re-run TableQueryBuilder with the new query string.
 *
 * Query-string contract (matches `Arqel\Table\TableQueryBuilder`):
 *   - `?search=foo`              global search
 *   - `?sort=column&direction=asc|desc`
 *   - `?page=N&per_page=M`
 *   - `?filter[name]=value`      one entry per filter
 *
 * `search` is debounced (300ms) so typing doesn't hammer the
 * backend; the rest fire immediately.
 */

import type { ActionSchema } from '@arqel-dev/types/actions';
import type { RecordType, ResourceIndexProps } from '@arqel-dev/types/resources';
import type { SortDirection } from '@arqel-dev/types/tables';
import { router, usePage } from '@inertiajs/react';
import { type JSX, useEffect, useMemo, useRef, useState } from 'react';
import { ActionMenu } from '../action/ActionMenu.js';
import { ResourceIndex } from '../resource/ResourceIndex.js';

type FilterValue = unknown;

function pruneEmptyFilters(values: Record<string, FilterValue>): Record<string, FilterValue> {
  const out: Record<string, FilterValue> = {};
  for (const [k, v] of Object.entries(values)) {
    if (v === undefined || v === null || v === '') continue;
    if (Array.isArray(v) && v.length === 0) continue;
    out[k] = v;
  }
  return out;
}

interface VisitParams {
  filters: Record<string, FilterValue>;
  search: string;
  sort?: { column: string | null; direction: string | null } | null;
  page?: number | undefined;
  perPage?: number | undefined;
}

function invokeAction(
  action: ActionSchema,
  record: { id: string | number } | null,
  formValues?: Record<string, unknown>,
): void {
  const recordId = record?.id;
  const url = action.url
    ? action.url.replace('{id}', String(recordId ?? ''))
    : `/arqel-dev/actions/${action.name}`;
  const method = action.method.toLowerCase() as 'get' | 'post' | 'put' | 'patch' | 'delete';
  const data: Record<string, unknown> = { ...(formValues ?? {}) };
  if (recordId !== undefined) data['record_id'] = recordId;

  router.visit(url, {
    method: method as never,
    data: data as never,
    preserveScroll: true,
  });
}

function buildQuery(params: VisitParams): Record<string, unknown> {
  const data: Record<string, unknown> = {};
  const filters = pruneEmptyFilters(params.filters);
  if (Object.keys(filters).length > 0) data['filter'] = filters;
  if (params.search) data['search'] = params.search;
  if (params.sort?.column) {
    data['sort'] = params.sort.column;
    if (params.sort.direction) data['direction'] = params.sort.direction;
  }
  if (params.page && params.page > 1) data['page'] = params.page;
  if (params.perPage) data['per_page'] = params.perPage;
  return data;
}

export default function ArqelIndexPage<TRecord extends RecordType = RecordType>(): JSX.Element {
  const page = usePage();
  const props = page.props as unknown as ResourceIndexProps<TRecord>;

  const initialFilters = useMemo(() => {
    if (typeof window === 'undefined') return {};
    const sp = new URLSearchParams(window.location.search);
    const fromUrl: Record<string, FilterValue> = {};
    for (const [key, value] of sp.entries()) {
      const m = key.match(/^filter\[([^\]]+)\]$/);
      if (m?.[1]) fromUrl[m[1]] = value;
    }
    return fromUrl;
  }, []);

  const [filterValues, setFilterValues] = useState<Record<string, FilterValue>>(initialFilters);
  const [searchValue, setSearchValue] = useState<string>(props.search ?? '');
  const [perPage, setPerPage] = useState<number | undefined>(props.pagination?.perPage);
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const visit = (data: Record<string, unknown>): void => {
    const url = typeof window !== 'undefined' ? window.location.pathname : page.url.split('?')[0];
    router.get(url ?? '/', data as Record<string, string | number | boolean | undefined>, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const currentSort = props.sort ?? null;

  const handleFilterChange = (name: string, value: FilterValue): void => {
    const nextFilters = { ...filterValues };
    if (value === undefined || value === null || value === '') {
      delete nextFilters[name];
    } else {
      nextFilters[name] = value;
    }
    setFilterValues(nextFilters);
    visit(buildQuery({ filters: nextFilters, search: searchValue, sort: currentSort, perPage }));
  };

  const handleClearFilters = (): void => {
    setFilterValues({});
    visit(buildQuery({ filters: {}, search: searchValue, sort: currentSort, perPage }));
  };

  const handleSearchChange = (value: string): void => {
    setSearchValue(value);
    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
    searchDebounceRef.current = setTimeout(() => {
      visit(buildQuery({ filters: filterValues, search: value, sort: currentSort, perPage }));
    }, 300);
  };

  useEffect(
    () => () => {
      if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
    },
    [],
  );

  const handleSortChange = (column: string, direction: SortDirection): void => {
    visit(
      buildQuery({
        filters: filterValues,
        search: searchValue,
        sort: { column, direction },
        perPage,
      }),
    );
  };

  const handlePageChange = (pageNum: number): void => {
    visit(
      buildQuery({
        filters: filterValues,
        search: searchValue,
        sort: currentSort,
        page: pageNum,
        perPage,
      }),
    );
  };

  const handlePerPageChange = (newPerPage: number): void => {
    setPerPage(newPerPage);
    visit(
      buildQuery({
        filters: filterValues,
        search: searchValue,
        sort: currentSort,
        perPage: newPerPage,
      }),
    );
  };

  const rowActionsList = props.actions?.row ?? [];
  const rowActions =
    rowActionsList.length > 0
      ? (record: TRecord) => (
          <ActionMenu
            actions={rowActionsList}
            onInvoke={(action, formValues) =>
              invokeAction(action, record as { id: string | number }, formValues)
            }
          />
        )
      : undefined;

  return (
    <ResourceIndex<TRecord>
      {...props}
      filterValues={filterValues}
      onFilterChange={handleFilterChange}
      onClearFilters={handleClearFilters}
      onSearchChange={handleSearchChange}
      onSortChange={handleSortChange}
      onPageChange={handlePageChange}
      onPerPageChange={handlePerPageChange}
      search={searchValue}
      {...(rowActions ? { rowActions } : {})}
    />
  );
}

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
import { ActionButton } from '../action/ActionButton.js';
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

/**
 * Resolve the row-action list for a single record (#140).
 *
 * Row actions are defined once at table level (label / icon / color /
 * confirmation / form), but `url(Closure)` and `disabled(Closure)`
 * actions depend on the row. The server emits those resolved values
 * per record under `record.arqel.actionOverrides`; here we merge them
 * onto the shared definitions so each row links to its own URL and
 * carries its own disabled state.
 *
 * When the server also emits `record.arqel.actions` (the visible +
 * executable action names) we filter the list against it so per-row
 * authorization/visibility is honoured.
 */
function resolveRecordActions(
  tableActions: ActionSchema[],
  record: {
    arqel?: {
      actions?: string[];
      actionOverrides?: Record<string, { url?: string; disabled?: true }>;
    };
  },
): ActionSchema[] {
  const visibleNames = record.arqel?.actions;
  const overrides = record.arqel?.actionOverrides ?? {};

  return tableActions
    .filter((action) => (visibleNames ? visibleNames.includes(action.name) : true))
    .map((action) => {
      const override = overrides[action.name];
      if (override === undefined) return action;
      return {
        ...action,
        ...(override.url !== undefined ? { url: override.url } : {}),
        ...(override.disabled === true ? { disabled: true as const } : {}),
      };
    });
}

function buildQuery(params: VisitParams): Record<string, unknown> {
  const data: Record<string, unknown> = {};
  const filters = pruneEmptyFilters(params.filters);
  if (Object.keys(filters).length > 0) data['filter'] = filters;
  if (params.search !== undefined) data['search'] = params.search;
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
  const [selectedIds, setSelectedIds] = useState<ReadonlyArray<string | number>>([]);
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

  const dispatchBulkAction = (action: ActionSchema): void => {
    // Every bulk action carries a stock url (core resolves
    // /admin/{slug}/bulk/{name} for any bulk action without an
    // explicit url), so there is no client-side fallback route.
    const url = action.url;
    if (url === undefined) {
      return;
    }
    const method = action.method.toLowerCase() as 'get' | 'post' | 'put' | 'patch' | 'delete';
    router.visit(url, {
      method: method as never,
      data: { record_ids: selectedIds as readonly (string | number)[] } as never,
      preserveScroll: true,
      onSuccess: () => setSelectedIds([]),
    });
  };

  const bulkActionsList = props.actions?.bulk ?? [];
  const bulkActions =
    bulkActionsList.length > 0 && selectedIds.length > 0 ? (
      <div className="flex items-center gap-2">
        {bulkActionsList.map((action) => (
          // Route through ActionButton so destructive bulk actions that set
          // requiresConfirmation open the ConfirmDialog before dispatching,
          // matching row-action behaviour. Non-confirming actions fire
          // immediately. The visit payload is unchanged.
          <ActionButton
            key={action.name}
            action={action}
            onInvoke={() => dispatchBulkAction(action)}
            size="sm"
          />
        ))}
      </div>
    ) : undefined;

  const rowActionsList = props.actions?.row ?? [];
  const rowActions =
    rowActionsList.length > 0
      ? (record: TRecord) => (
          <ActionMenu
            // Resolve actions against this row so closure-URL /
            // closure-disabled actions get their per-record values
            // (#140) instead of the table-level placeholder.
            actions={resolveRecordActions(rowActionsList, record)}
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
      selectedIds={selectedIds}
      onSelectionChange={(ids) => setSelectedIds(ids)}
      {...(rowActions ? { rowActions } : {})}
      {...(bulkActions ? { bulkActions } : {})}
    />
  );
}

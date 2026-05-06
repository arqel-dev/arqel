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
 * `search` is debounced (300ms) so typing doesn't hammer the
 * backend; the rest fire immediately.
 */

import type { RecordType, ResourceIndexProps } from '@arqel-dev/types/resources';
import type { SortDirection } from '@arqel-dev/types/tables';
import { router, usePage } from '@inertiajs/react';
import { type JSX, useEffect, useMemo, useRef, useState } from 'react';
import { ResourceIndex } from '../resource/ResourceIndex.js';

type FilterValue = unknown;

function pruneEmpty(values: Record<string, FilterValue>): Record<string, FilterValue> {
  const out: Record<string, FilterValue> = {};
  for (const [k, v] of Object.entries(values)) {
    if (v === undefined || v === null || v === '') continue;
    if (Array.isArray(v) && v.length === 0) continue;
    out[k] = v;
  }
  return out;
}

export default function ArqelIndexPage<TRecord extends RecordType = RecordType>(): JSX.Element {
  const page = usePage();
  const props = page.props as unknown as ResourceIndexProps<TRecord>;

  const initialFilters = useMemo(() => {
    const fromUrl: Record<string, FilterValue> = {};
    if (typeof window !== 'undefined') {
      const search = new URLSearchParams(window.location.search);
      for (const [key, value] of search.entries()) {
        if (key === 'search' || key === 'sort' || key === 'direction' || key === 'page') continue;
        fromUrl[key] = value;
      }
    }
    return fromUrl;
  }, []);

  const [filterValues, setFilterValues] = useState<Record<string, FilterValue>>(initialFilters);
  const [searchValue, setSearchValue] = useState<string>(props.search ?? '');
  const searchDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const visit = (next: Record<string, FilterValue>): void => {
    const data = pruneEmpty(next) as Record<string, string | number | boolean | undefined>;
    const url = typeof window !== 'undefined' ? window.location.pathname : page.url.split('?')[0];
    router.get(url ?? '/', data, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const buildBase = (): Record<string, FilterValue> => ({
    ...filterValues,
    ...(searchValue ? { search: searchValue } : {}),
    ...(props.sort?.column ? { sort: props.sort.column, direction: props.sort.direction } : {}),
  });

  const handleFilterChange = (name: string, value: FilterValue): void => {
    const next = { ...filterValues };
    if (value === undefined || value === null || value === '') {
      delete next[name];
    } else {
      next[name] = value;
    }
    setFilterValues(next);
    visit({
      ...next,
      ...(searchValue ? { search: searchValue } : {}),
      ...(props.sort?.column ? { sort: props.sort.column, direction: props.sort.direction } : {}),
    });
  };

  const handleClearFilters = (): void => {
    setFilterValues({});
    visit({
      ...(searchValue ? { search: searchValue } : {}),
      ...(props.sort?.column ? { sort: props.sort.column, direction: props.sort.direction } : {}),
    });
  };

  const handleSearchChange = (value: string): void => {
    setSearchValue(value);
    if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
    searchDebounceRef.current = setTimeout(() => {
      visit({
        ...filterValues,
        ...(value ? { search: value } : {}),
        ...(props.sort?.column ? { sort: props.sort.column, direction: props.sort.direction } : {}),
      });
    }, 300);
  };

  useEffect(
    () => () => {
      if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
    },
    [],
  );

  const handleSortChange = (column: string, direction: SortDirection): void => {
    visit({ ...buildBase(), sort: column, direction });
  };

  const handlePageChange = (pageNum: number): void => {
    visit({ ...buildBase(), page: pageNum });
  };

  const handlePerPageChange = (perPage: number): void => {
    visit({ ...buildBase(), per_page: perPage });
  };

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
    />
  );
}

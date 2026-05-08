/**
 * Default Inertia page for `arqel::dashboard`.
 *
 * Bridges the Inertia payload emitted by
 * `Arqel\Widgets\Http\Controllers\DashboardController::show` into the
 * existing `<DashboardGrid>` component. The grid already handles the
 * heading/description chrome, the filter bar and the responsive
 * widget grid (column spans, polling, deferred fetch), so this page
 * only owns one piece of glue: lifting filter state up into the URL
 * so deep-link refreshes rehydrate cleanly.
 *
 * Filter changes navigate via `router.get(path, { filters: ... })`,
 * matching `DashboardController::show` which reads
 * `$request->input('filters', [])` and forwards it as `filterValues`.
 */

import { router, usePage } from '@inertiajs/react';
import type { JSX } from 'react';
import { DashboardGrid, type DashboardPayload } from '../widgets/index.js';

interface ArqelDashboardPageProps {
  dashboard: DashboardPayload;
  filterValues?: Record<string, unknown>;
  [key: string]: unknown;
}

export default function ArqelDashboardPage(): JSX.Element {
  const { dashboard, filterValues } = usePage<ArqelDashboardPageProps>().props;

  const handleFilterChange = (name: string, value: unknown): void => {
    const current = filterValues ?? {};
    const next: Record<string, unknown> = { ...current };
    if (value === undefined || value === null || value === '') {
      delete next[name];
    } else {
      next[name] = value;
    }

    const path = dashboard.path ?? `/admin/dashboards/${dashboard.id}`;
    const params = (Object.keys(next).length > 0 ? { filters: next } : {}) as never;

    router.get(path, params, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  return (
    <DashboardGrid
      dashboard={dashboard}
      filterValues={filterValues ?? {}}
      onFilterChange={handleFilterChange}
    />
  );
}

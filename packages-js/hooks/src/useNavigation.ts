/**
 * `useNavigation` — read panel navigation from Inertia shared props.
 *
 * Server emits a flat or grouped tree under `panel.navigation`; this hook
 * normalises it to a `NavigationItem[]`-like shape callsites can render.
 */

import type { SharedProps } from '@arqel-dev/types/inertia';
import { usePage } from '@inertiajs/react';

export interface NavigationItemPayload {
  label: string;
  url: string;
  icon?: string | null;
  badge?: string | number | null;
  active?: boolean;
  group?: string | null;
  children?: NavigationItemPayload[];
}

export interface UseNavigationResult {
  items: NavigationItemPayload[];
}

interface PanelWithNav {
  navigation?: NavigationItemPayload[];
}

export function useNavigation(): UseNavigationResult {
  const page = usePage();
  const props = page.props as unknown as SharedProps;
  const panel = props.panel as (SharedProps['panel'] & PanelWithNav) | null;
  const items = Array.isArray(panel?.navigation) ? panel.navigation : [];
  return { items };
}

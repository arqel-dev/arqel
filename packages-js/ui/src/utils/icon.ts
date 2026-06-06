/**
 * Resolve a lucide icon NAME to its React component.
 *
 * Producers (PHP `getNavigationIcon()`, `IconColumn`, badge icon maps) emit
 * kebab-case lucide names like `'file-text'` or `'life-buoy'`. lucide-react
 * exposes components in PascalCase under the `icons` map, so we normalise the
 * name (`'file-text'` → `'FileText'`) before lookup. Returns `undefined` for an
 * unknown or empty name so callers can render a graceful fallback.
 */

import { icons as lucideIcons } from 'lucide-react';

export type LucideIconComponent = (typeof lucideIcons)[keyof typeof lucideIcons];

export function resolveLucideIcon(
  name: string | null | undefined,
): LucideIconComponent | undefined {
  if (!name) return undefined;
  const pascal = name
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join('');
  return lucideIcons[pascal as keyof typeof lucideIcons];
}

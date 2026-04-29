/**
 * `<Breadcrumbs>` — renders the panel's breadcrumb trail.
 *
 * Items can be passed explicitly, or auto-resolved from Inertia shared
 * props (`panel.breadcrumbs`, populated server-side by
 * `Arqel\Nav\BreadcrumbsBuilder`). Each item is a link unless it's the
 * last entry, which is rendered as plain text and marked
 * `aria-current="page"`.
 *
 * `usePage()` is only invoked when `items` is omitted — explicit-items
 * usage works outside Inertia (e.g. dashboard widgets, tests).
 */

import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { cn } from '../utils/cn.js';

export interface BreadcrumbItem {
  label: string;
  url?: string | null;
}

export interface BreadcrumbsProps {
  items?: BreadcrumbItem[];
  separator?: ReactNode;
  className?: string;
}

interface PanelWithCrumbs {
  panel?: { breadcrumbs?: BreadcrumbItem[] } | null;
}

export function Breadcrumbs(props: BreadcrumbsProps) {
  if (props.items === undefined) {
    return <SharedBreadcrumbs {...props} />;
  }
  return <BreadcrumbsList {...props} items={props.items} />;
}

function SharedBreadcrumbs(props: BreadcrumbsProps) {
  const page = usePage();
  const items = (page.props as unknown as PanelWithCrumbs).panel?.breadcrumbs ?? [];
  return <BreadcrumbsList {...props} items={items} />;
}

function BreadcrumbsList({
  items,
  separator = <span aria-hidden="true">/</span>,
  className,
}: BreadcrumbsProps & { items: BreadcrumbItem[] }) {
  if (items.length === 0) return null;

  return (
    <nav aria-label="Breadcrumb" className={cn('text-sm', className)}>
      <ol className="flex flex-wrap items-center gap-2 text-[var(--color-arqel-muted-fg)]">
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          return (
            <li key={`${item.label}-${item.url ?? 'leaf'}`} className="flex items-center gap-2">
              {isLast || !item.url ? (
                <span
                  aria-current={isLast ? 'page' : undefined}
                  className="text-[var(--color-arqel-fg)]"
                >
                  {item.label}
                </span>
              ) : (
                <a href={item.url} className="hover:underline">
                  {item.label}
                </a>
              )}
              {!isLast && separator}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}

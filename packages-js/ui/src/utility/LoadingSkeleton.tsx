/**
 * `<LoadingSkeleton>` — animated placeholder block.
 *
 * Variants:
 *   - `line`: single text line (default)
 *   - `block`: rectangular block (cards, hero areas)
 *   - `circle`: circular (avatars)
 *
 * Heights / widths default to readable values; override via Tailwind
 * classes or explicit `height` / `width` props for bespoke layouts.
 */

import { cn } from '../utils/cn.js';

export type SkeletonVariant = 'line' | 'block' | 'circle';

export interface LoadingSkeletonProps {
  variant?: SkeletonVariant;
  width?: number | string;
  height?: number | string;
  count?: number;
  className?: string;
}

const VARIANT_CLASSES: Record<SkeletonVariant, string> = {
  line: 'h-4 w-full',
  block: 'h-24 w-full',
  circle: 'h-10 w-10 rounded-full',
};

export function LoadingSkeleton({
  variant = 'line',
  width,
  height,
  count = 1,
  className,
}: LoadingSkeletonProps) {
  const items = Array.from({ length: count }, (_, i) => i);
  return (
    <div className="flex flex-col gap-2" aria-hidden="true">
      {items.map((i) => (
        <div
          key={i}
          style={{ width, height }}
          className={cn(
            'animate-pulse bg-[var(--color-arqel-muted)]',
            variant !== 'circle' && 'rounded-[var(--radius-arqel-sm)]',
            VARIANT_CLASSES[variant],
            className,
          )}
        />
      ))}
    </div>
  );
}

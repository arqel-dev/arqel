/**
 * `useBreakpoint` — current Tailwind v4 breakpoint via `matchMedia`.
 *
 * SSR-safe: returns `'sm'` until mount, then subscribes to media queries.
 * Defaults follow Tailwind v4 (sm 640, md 768, lg 1024, xl 1280, 2xl 1536).
 */

import { useEffect, useState } from 'react';

export type Breakpoint = 'sm' | 'md' | 'lg' | 'xl' | '2xl';

const QUERIES: Array<{ name: Breakpoint; min: number }> = [
  { name: '2xl', min: 1536 },
  { name: 'xl', min: 1280 },
  { name: 'lg', min: 1024 },
  { name: 'md', min: 768 },
  { name: 'sm', min: 0 },
];

function resolveBreakpoint(width: number): Breakpoint {
  for (const q of QUERIES) {
    if (width >= q.min) return q.name;
  }
  return 'sm';
}

export function useBreakpoint(): Breakpoint {
  const [bp, setBp] = useState<Breakpoint>('sm');

  useEffect(() => {
    if (typeof window === 'undefined') return;

    const update = () => setBp(resolveBreakpoint(window.innerWidth));
    update();
    window.addEventListener('resize', update);
    return () => window.removeEventListener('resize', update);
  }, []);

  return bp;
}

/**
 * `cn` — clsx + tailwind-merge.
 *
 * Joins class names and de-duplicates conflicting Tailwind utilities so
 * later values override earlier ones (e.g. `cn('p-2', 'p-4')` → `'p-4'`).
 */

import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}

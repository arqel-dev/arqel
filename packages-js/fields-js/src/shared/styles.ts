/**
 * Shared input styling — keeps the visual contract consistent across
 * every Field component without forcing a wrapper component.
 */

import { cn } from '@arqel-dev/ui/utils';

export const inputClasses = cn(
  'h-9 w-full rounded-[var(--radius-arqel-sm)] border border-[var(--color-arqel-input)]',
  'bg-[var(--color-arqel-bg)] px-3 text-sm text-[var(--color-arqel-fg)]',
  'placeholder:text-[var(--color-arqel-muted-fg)]',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]',
  'disabled:cursor-not-allowed disabled:opacity-50',
  'aria-invalid:border-[var(--color-arqel-destructive)]',
);

export const checkboxClasses = cn(
  'h-4 w-4 rounded border-[var(--color-arqel-input)]',
  'text-[var(--color-arqel-primary)]',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-arqel-ring)]',
  'disabled:cursor-not-allowed disabled:opacity-50',
);

/**
 * Shared input styling — keeps the visual contract consistent across
 * every Field component without forcing a wrapper component.
 */

import { cn } from '@arqel-dev/ui/utils';

export const inputClasses = cn(
  'h-9 w-full rounded-sm border border-[var(--input)]',
  'bg-background px-3 text-sm text-foreground',
  'placeholder:text-muted-foreground',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
  'disabled:cursor-not-allowed disabled:opacity-50',
  'aria-invalid:border-destructive',
);

export const checkboxClasses = cn(
  'h-4 w-4 rounded border-[var(--input)]',
  'text-primary',
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
  'disabled:cursor-not-allowed disabled:opacity-50',
);

import { usePage } from '@inertiajs/react';

/**
 * Shared locale-aware formatting helpers for the marketplace app.
 *
 * The active locale is read from the Inertia i18n shared prop
 * (`usePage().props.i18n.locale`). Underscore locales such as `pt_BR`
 * are mapped to their BCP-47 equivalent (`pt-BR`) before being handed
 * to the Intl APIs. When no locale is shared (e.g. plain unit tests that
 * mock `@inertiajs/react` without a page), formatting falls back to `en`.
 */

const DEFAULT_LOCALE = 'en';

type I18nProp = {
  locale?: string | null;
};

type PageWithI18n = {
  props?: {
    i18n?: I18nProp | null;
  };
};

/** Map an underscore locale (pt_BR) to a BCP-47 tag (pt-BR). */
export function toBcp47(locale: string): string {
  return locale.replace('_', '-');
}

/**
 * Resolve the active locale from the Inertia i18n shared prop as a
 * BCP-47 tag. Falls back to `en` when no locale is shared.
 */
export function useActiveLocale(): string {
  const page = usePage() as unknown as PageWithI18n;
  const raw = page?.props?.i18n?.locale;
  return toBcp47(raw !== null && raw !== undefined && raw !== '' ? raw : DEFAULT_LOCALE);
}

/** Format an amount of minor units (cents) as currency in the given locale. */
export function formatCurrency(cents: number, currency: string, locale: string): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(cents / 100);
}

/** Format a number using locale-aware compact notation (e.g. 12K / 12 mil). */
export function formatCompact(value: number, locale: string): string {
  return new Intl.NumberFormat(locale, { notation: 'compact' }).format(value);
}

/** Format an ISO/date string for the given locale. Returns the dash fallback when empty/invalid. */
export function formatDate(
  value: string | null | undefined,
  locale: string,
  options?: Intl.DateTimeFormatOptions,
): string {
  if (value === null || value === undefined || value === '') return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return new Intl.DateTimeFormat(locale, options).format(date);
}

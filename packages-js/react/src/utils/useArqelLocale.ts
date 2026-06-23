import { usePage } from '@inertiajs/react';

/**
 * Normalize an Arqel locale string (e.g. the underscore-style `pt_BR` Laravel
 * ships) into a BCP-47 language tag (`pt-BR`) suitable for `Intl.*` and the
 * `toLocale*` family. Underscores become hyphens; whitespace is trimmed.
 *
 * Returns `undefined` for empty/invalid input so callers can fall back to the
 * runtime default by passing `undefined` to `Intl`.
 */
export function toBcp47(locale: string | null | undefined): string | undefined {
  if (locale === null || locale === undefined) return undefined;
  const trimmed = locale.trim();
  if (trimmed === '') return undefined;
  return trimmed.replace(/_/g, '-');
}

/**
 * Hook returning the active Arqel panel locale as a BCP-47 tag, read from the
 * shared Inertia `props.i18n.locale` set by `HandleArqelInertiaRequests` — the
 * same source `<I18nProvider>` and `useArqelTranslations()` consume.
 *
 * Lets framework UI primitives (`@arqel-dev/ui`) format dates/numbers/relative
 * times in the active locale without depending on `@arqel-dev/i18n`, keeping
 * the package graph acyclic (`types → react → {hooks, ui} → i18n`).
 *
 * Falls back to `navigator.language` (browser) and finally `'en'` when the prop
 * is absent (a non-Arqel page, SSR snapshot, or a locale gap), so `Intl` always
 * receives a deterministic tag.
 */
export function useArqelLocale(): string {
  const page = usePage();
  const i18n = (page?.props as Record<string, unknown> | undefined)?.['i18n'];
  const raw =
    i18n !== null && typeof i18n === 'object' && 'locale' in i18n
      ? (i18n as { locale?: unknown }).locale
      : undefined;
  if (typeof raw === 'string') {
    const tag = toBcp47(raw);
    if (tag !== undefined) return tag;
  }
  if (typeof navigator !== 'undefined' && typeof navigator.language === 'string') {
    const tag = toBcp47(navigator.language);
    if (tag !== undefined) return tag;
  }
  return 'en';
}

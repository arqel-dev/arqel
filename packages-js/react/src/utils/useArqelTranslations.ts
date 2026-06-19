import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { type TranslationDictionary, translate } from './translate.js';

/**
 * Hook returning a `t(key, replacements)` bound to the Arqel translation
 * dictionary shared by `HandleArqelInertiaRequests` under `props.i18n.
 * translations` — the same source the `<I18nProvider>` reads.
 *
 * This lets framework UI primitives (`@arqel-dev/ui`) translate their own
 * chrome (pagination, form actions, dialogs…) without depending on the
 * higher-level `@arqel-dev/i18n` package, keeping the package hierarchy
 * acyclic (`types → react → {hooks, ui} → i18n`).
 *
 * Each call takes an optional `fallback` — the component's existing English
 * literal. When the key is absent from the shared dictionary (a non-Arqel page,
 * or a translation gap) the fallback is rendered instead of the raw key, so the
 * UI never regresses to showing `table.pagination.previous`.
 */
export function useArqelTranslations(): (
  key: string,
  fallback?: string,
  replacements?: Record<string, string | number>,
) => string {
  const page = usePage();
  const i18n = (page?.props as Record<string, unknown> | undefined)?.['i18n'];
  const dict: TranslationDictionary =
    i18n !== null && typeof i18n === 'object' && 'translations' in i18n
      ? (((i18n as { translations?: unknown }).translations as TranslationDictionary) ?? {})
      : {};

  return useCallback(
    (key: string, fallback?: string, replacements?: Record<string, string | number>): string => {
      const resolved = translate(dict, key, replacements ? { replacements } : undefined);
      // `translate` returns the key itself when missing — swap in the human
      // fallback in that case so callers can keep their literal as a default.
      return resolved === key && fallback !== undefined ? fallback : resolved;
    },
    [dict],
  );
}

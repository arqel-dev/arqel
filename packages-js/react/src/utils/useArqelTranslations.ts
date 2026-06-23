import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';
import { selectPluralForm, type TranslationDictionary, translate } from './translate.js';
import { useArqelLocale } from './useArqelLocale.js';

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
 *
 * When `replacements.count` is a number the value is treated as a
 * pluralizable string (`{one} :count item|{other} :count items`) and the
 * matching form is selected via `Intl.PluralRules` in the active panel locale
 * before `:placeholder` tokens are substituted — so ":count selected"
 * singularizes correctly per locale instead of relying on a "(s)" hack. The
 * `fallback` is pluralized the same way, so a non-Arqel page still reads right.
 */
export function useArqelTranslations(): (
  key: string,
  fallback?: string,
  replacements?: Record<string, string | number>,
) => string {
  const page = usePage();
  const locale = useArqelLocale();
  const i18n = (page?.props as Record<string, unknown> | undefined)?.['i18n'];
  // Memoize the dictionary on the `i18n` prop identity. Recomputing it inline
  // on every render produced a fresh object reference each time, which made the
  // `useCallback([dict])` below return a new `t` on every render and defeated
  // memoization for consumers that list `t` in their effect/memo dep arrays.
  const dict = useMemo<TranslationDictionary>(
    () =>
      i18n !== null && typeof i18n === 'object' && 'translations' in i18n
        ? (((i18n as { translations?: unknown }).translations as TranslationDictionary) ?? {})
        : {},
    [i18n],
  );

  return useCallback(
    (key: string, fallback?: string, replacements?: Record<string, string | number>): string => {
      const count = typeof replacements?.['count'] === 'number' ? replacements['count'] : undefined;
      const resolved = translate(
        dict,
        key,
        replacements
          ? { replacements, locale, ...(count !== undefined ? { count } : {}) }
          : undefined,
      );
      // `translate` returns the key itself when missing — swap in the human
      // fallback in that case so callers can keep their literal as a default.
      if (resolved === key && fallback !== undefined) {
        // Pluralize the fallback too so the literal default stays correct.
        const form = count === undefined ? fallback : selectPluralForm(fallback, count, locale);
        return replacements
          ? form.replace(/:([a-zA-Z0-9_]+)/g, (match, name: string) => {
              const replacement = replacements[name];
              return replacement === undefined ? match : String(replacement);
            })
          : form;
      }
      return resolved;
    },
    [dict, locale],
  );
}

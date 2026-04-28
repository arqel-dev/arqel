import { useCallback } from 'react';

export type TranslationDictionary = Record<string, unknown>;

export interface TranslateOptions {
  /** Substitution map for `:placeholder` tokens. */
  replacements?: Record<string, string | number>;
}

/**
 * Resolve a dotted key path against the given dictionary.
 * Returns `undefined` when any segment misses.
 */
function lookup(dict: TranslationDictionary, path: string): unknown {
  return path.split('.').reduce<unknown>((acc, segment) => {
    if (acc !== null && typeof acc === 'object' && segment in acc) {
      return (acc as Record<string, unknown>)[segment];
    }
    return undefined;
  }, dict);
}

function applyReplacements(value: string, replacements?: Record<string, string | number>): string {
  if (replacements === undefined) {
    return value;
  }

  return Object.entries(replacements).reduce(
    (acc, [key, replacement]) => acc.replaceAll(`:${key}`, String(replacement)),
    value,
  );
}

/**
 * Translate a key against a dictionary. Returns the key itself when
 * missing — never returns `undefined` so JSX never renders nothing.
 */
export function translate(
  dict: TranslationDictionary,
  key: string,
  options?: TranslateOptions,
): string {
  const value = lookup(dict, key);

  if (typeof value !== 'string') {
    return key;
  }

  return applyReplacements(value, options?.replacements);
}

/**
 * Hook that returns a memoised `t(key, replacements)` bound to the
 * supplied dictionary. Apps build the dictionary from
 * `usePage<SharedProps>().props.translations`.
 */
export function useTranslator(
  dict: TranslationDictionary,
): (key: string, replacements?: Record<string, string | number>) => string {
  return useCallback(
    (key: string, replacements?: Record<string, string | number>): string =>
      translate(dict, key, replacements ? { replacements } : undefined),
    [dict],
  );
}

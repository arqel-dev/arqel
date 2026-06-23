import { useCallback } from 'react';

export type TranslationDictionary = Record<string, unknown>;

export interface TranslateOptions {
  /** Substitution map for `:placeholder` tokens. */
  replacements?: Record<string, string | number>;
  /**
   * When set, the resolved value is treated as a pluralizable string and the
   * matching form is selected for this count before `:placeholder` tokens are
   * substituted. See {@link selectPluralForm} for the supported syntax.
   */
  count?: number;
  /** BCP-47 locale tag driving `Intl.PluralRules` (defaults to `'en'`). */
  locale?: string;
}

/**
 * Pick the correct plural form from a Laravel-style pluralizable string.
 *
 * A pluralizable value is a list of forms separated by `|`. Each form may be
 * prefixed by an explicit selector so authors keep full control per locale:
 *
 *   - `{0} ...` / `{1} ...` — exact-count match.
 *   - `[2,*] ...` / `[2,4] ...` — inclusive numeric range (`*` = unbounded).
 *   - `{one} ...` / `{other} ...` — CLDR category (matched via `Intl.PluralRules`).
 *
 * Forms without a selector fall back to positional order
 * (`singular|plural` → index 0 for `one`, index 1 otherwise), matching
 * Laravel's `trans_choice` convention. This lets `pt_BR` declare three forms
 * while `en` declares two, with the right one chosen per the active locale.
 */
export function selectPluralForm(value: string, count: number, locale?: string): string {
  const forms = value.split('|').map((segment) => segment.trim());
  if (forms.length === 1) {
    return forms[0] ?? value;
  }

  const tagged: Array<{ selector: string | null; text: string }> = forms.map((form) => {
    const exact = /^\{\s*(-?\d+|one|other|zero|two|few|many)\s*\}\s*(.*)$/s.exec(form);
    if (exact) {
      return { selector: (exact[1] ?? '').trim(), text: (exact[2] ?? '').trim() };
    }
    const range = /^\[\s*(-?\d+)\s*,\s*(\*|-?\d+)\s*\]\s*(.*)$/s.exec(form);
    if (range) {
      const low = Number(range[1]);
      const highRaw = range[2] ?? '*';
      const high = highRaw === '*' ? Number.POSITIVE_INFINITY : Number(highRaw);
      if (count >= low && count <= high) {
        return { selector: '__range_match__', text: (range[3] ?? '').trim() };
      }
      return { selector: '__range_nomatch__', text: (range[3] ?? '').trim() };
    }
    return { selector: null, text: form };
  });

  // 1. Exact numeric / matched-range selectors win.
  for (const form of tagged) {
    if (form.selector === String(count) || form.selector === '__range_match__') {
      return form.text;
    }
  }

  // 2. CLDR category via Intl.PluralRules, when forms are category-tagged.
  let category = 'other';
  try {
    category = new Intl.PluralRules(locale ?? 'en').select(count);
  } catch {
    category = count === 1 ? 'one' : 'other';
  }
  const byCategory = tagged.find((form) => form.selector === category);
  if (byCategory) {
    return byCategory.text;
  }
  const otherCategory = tagged.find((form) => form.selector === 'other');
  if (otherCategory) {
    return otherCategory.text;
  }

  // 3. Positional fallback (`singular|plural`): index 0 for `one`, else last.
  const positional = tagged.filter((form) => form.selector === null);
  if (positional.length > 0) {
    if (category === 'one' && positional[0]) {
      return positional[0].text;
    }
    return (positional[positional.length - 1] ?? positional[0])?.text ?? value;
  }

  return tagged[tagged.length - 1]?.text ?? value;
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

  // Match whole `:token` placeholders in one pass so a short key never
  // clobbers a longer one (`:to` must not eat the start of `:total`), and a
  // substituted value can't be re-substituted by a later key.
  return value.replace(/:([a-zA-Z0-9_]+)/g, (match, name: string) => {
    const replacement = replacements[name];
    return replacement === undefined ? match : String(replacement);
  });
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

  const form =
    options?.count === undefined ? value : selectPluralForm(value, options.count, options.locale);

  return applyReplacements(form, options?.replacements);
}

/**
 * Hook that returns a memoised `t(key, replacements)` bound to the
 * supplied dictionary. Apps build the dictionary from
 * `usePage<SharedProps>().props.translations`.
 *
 * Like {@link useArqelTranslations}, when `replacements.count` is a number the
 * resolved value is treated as a pluralizable string and the matching form is
 * selected via `Intl.PluralRules` in `locale` (BCP-47) before `:placeholder`
 * tokens are substituted. Pass `locale` so pluralization and number-aware
 * forms honor the active panel locale instead of defaulting to `'en'`.
 */
export function useTranslator(
  dict: TranslationDictionary,
  locale?: string,
): (key: string, replacements?: Record<string, string | number>) => string {
  return useCallback(
    (key: string, replacements?: Record<string, string | number>): string => {
      if (replacements === undefined) {
        return translate(dict, key, locale !== undefined ? { locale } : undefined);
      }
      const count = typeof replacements['count'] === 'number' ? replacements['count'] : undefined;
      return translate(dict, key, {
        replacements,
        ...(locale !== undefined ? { locale } : {}),
        ...(count !== undefined ? { count } : {}),
      });
    },
    [dict, locale],
  );
}

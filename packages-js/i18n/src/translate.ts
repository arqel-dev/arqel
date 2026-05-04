import type { TranslateFn, TranslationDictionary } from './types';

/**
 * Walks the dotted-path `key` through `dict`, returning the leaf string
 * or — when missing — the original key. This mirrors Laravel's `__()`
 * fallback behaviour, keeping missing keys visible during development
 * without throwing in production.
 */
function lookup(dict: TranslationDictionary, key: string): string | undefined {
  const segments = key.split('.');
  let cursor: string | TranslationDictionary | undefined = dict;
  for (const segment of segments) {
    if (cursor === undefined || typeof cursor === 'string') {
      return undefined;
    }
    cursor = cursor[segment];
  }
  return typeof cursor === 'string' ? cursor : undefined;
}

/**
 * Replaces `:placeholder` tokens with values from `params`. Numeric
 * values are coerced to strings — keep the API symmetric with Laravel
 * `__('greeting', ['name' => $name])`.
 */
function interpolate(template: string, params?: Readonly<Record<string, string | number>>): string {
  if (params === undefined) {
    return template;
  }
  return template.replace(/:([a-zA-Z0-9_]+)/g, (match, name: string) => {
    const value = params[name];
    return value === undefined ? match : String(value);
  });
}

export function buildTranslator(dict: TranslationDictionary): TranslateFn {
  return (key, params) => {
    const found = lookup(dict, key);
    return found === undefined ? key : interpolate(found, params);
  };
}

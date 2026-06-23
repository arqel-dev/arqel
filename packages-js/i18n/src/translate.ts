import { translate as coreTranslate } from '@arqel-dev/react/utils';
import type { TranslateFn, TranslationDictionary } from './types';

/**
 * Builds a translator bound to `dict`, delegating to the SAME pluralization-
 * and locale-aware `translate()` core that `@arqel-dev/react`'s
 * `useArqelTranslations()` uses. This keeps the provider's `t()` and the hook
 * in lock-step: one page, one dictionary, one resolution semantics.
 *
 * Behaviour mirrors Laravel's `__()`:
 * - dotted-path lookup (`actions.create` → `dict.actions.create`);
 * - missing keys return the key itself (visible during development);
 * - `:placeholder` tokens are substituted from `params`;
 * - when `params.count` is a number the value is treated as a pluralizable
 *   string (`{one} :count item|{other} :count items`) and the matching form is
 *   selected via `Intl.PluralRules` in `locale` before substitution.
 *
 * `locale` should be a BCP-47 tag (`pt-BR`); pass the active panel locale so
 * pluralization matches the rendered language.
 */
export function buildTranslator(dict: TranslationDictionary, locale?: string): TranslateFn {
  return (key, params) => {
    const count = typeof params?.['count'] === 'number' ? params['count'] : undefined;
    return coreTranslate(
      dict as Record<string, unknown>,
      key,
      params
        ? {
            replacements: params,
            ...(locale !== undefined ? { locale } : {}),
            ...(count !== undefined ? { count } : {}),
          }
        : undefined,
    );
  };
}

/**
 * Translation values are stored as a nested object — exactly as Laravel
 * `lang/{locale}/arqel.php` returns. Lookup is dotted-path:
 * `actions.create` → `translations.actions.create`.
 */
export type TranslationDictionary = {
  readonly [key: string]: string | TranslationDictionary;
};

export type I18nSharedProps = {
  locale: string;
  available: readonly string[];
  translations: TranslationDictionary;
};

export type TranslateFn = (
  key: string,
  params?: Readonly<Record<string, string | number>>,
) => string;

export type I18nContextValue = {
  locale: string;
  available: readonly string[];
  translations: TranslationDictionary;
  t: TranslateFn;
};

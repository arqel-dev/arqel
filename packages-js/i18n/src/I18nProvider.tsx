import { usePage } from '@inertiajs/react';
import { createContext, type ReactNode, useContext, useMemo } from 'react';
import { buildTranslator } from './translate';
import type { I18nContextValue, I18nSharedProps } from './types';

const I18nContext = createContext<I18nContextValue | null>(null);

type I18nProviderProps = {
  children: ReactNode;
  /**
   * Override the shared-prop `i18n` payload (useful in tests or in
   * non-Inertia hosts). When omitted, falls back to
   * `usePage().props.i18n`.
   */
  i18n?: I18nSharedProps;
  /** Fallback locale when neither `i18n` nor Inertia shared props supply one. */
  fallbackLocale?: string;
};

/**
 * Provider que torna `useTranslation()` disponível abaixo. Lê os dados
 * de `usePage().props.i18n` injetados pelo middleware PHP (ver
 * `Arqel\Core\Http\Middleware\HandleArqelInertiaRequests`). Em testes
 * ou em ambientes sem Inertia, a prop `i18n` pode ser passada
 * diretamente.
 */
export function I18nProvider({
  children,
  i18n,
  fallbackLocale = 'en',
}: I18nProviderProps): JSX.Element {
  const sharedI18n = useSharedI18n();
  const resolved: I18nSharedProps = i18n ??
    sharedI18n ?? {
      locale: fallbackLocale,
      available: [fallbackLocale],
      translations: {},
    };

  const value = useMemo<I18nContextValue>(
    () => ({
      locale: resolved.locale,
      available: resolved.available,
      translations: resolved.translations,
      t: buildTranslator(resolved.translations),
    }),
    [resolved.locale, resolved.available, resolved.translations],
  );

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

function useSharedI18n(): I18nSharedProps | undefined {
  // `usePage` is always called unconditionally to honour Rules of
  // Hooks. When Inertia is not bootstrapped, our test mock returns
  // `{ props: {} }` so the lookup just yields `undefined` here.
  const page = usePage();
  const i18n = (page?.props as Record<string, unknown> | undefined)?.i18n;
  return isI18nSharedProps(i18n) ? i18n : undefined;
}

function isI18nSharedProps(value: unknown): value is I18nSharedProps {
  if (typeof value !== 'object' || value === null) {
    return false;
  }
  const v = value as Record<string, unknown>;
  return (
    typeof v.locale === 'string' &&
    Array.isArray(v.available) &&
    typeof v.translations === 'object' &&
    v.translations !== null
  );
}

export function useI18nContext(): I18nContextValue {
  const ctx = useContext(I18nContext);
  if (ctx === null) {
    throw new Error('[@arqel/i18n] useTranslation/useI18n must be used inside an <I18nProvider>.');
  }
  return ctx;
}

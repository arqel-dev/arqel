import { useI18nContext } from './I18nProvider';
import type { TranslateFn } from './types';

/**
 * `useTranslation()` retorna o tradutor já bound ao locale ativo do
 * provider. Mirror compacto da assinatura do Laravel `__()`:
 *
 * ```tsx
 * const t = useTranslation();
 * t('actions.save'); // → "Save" / "Salvar"
 * t('messages.welcome', { name: 'Diogo' });
 * ```
 */
export function useTranslation(): TranslateFn {
  return useI18nContext().t;
}

export function useI18n() {
  const { t, locale, available, translations } = useI18nContext();
  return { t, locale, available, translations };
}

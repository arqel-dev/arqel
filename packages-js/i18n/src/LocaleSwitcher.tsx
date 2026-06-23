import {
  Label,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@arqel-dev/ui';
import { router } from '@inertiajs/react';
import { type ReactElement, useEffect, useId } from 'react';
import { useI18nContext } from './I18nProvider';

type LocaleSwitcherProps = {
  /** Endpoint POST que persiste o locale na sessão (default `/admin/locale`). */
  endpoint?: string;
  /** Label visível acima do select (default usa `t('arqel.locale.switch')`). */
  label?: string;
  /** Override do mapa locale → display name. */
  labels?: Readonly<Record<string, string>>;
  className?: string;
};

const DEFAULT_LABELS: Readonly<Record<string, string>> = {
  en: 'English',
  pt_BR: 'Português (BR)',
  'pt-BR': 'Português (BR)',
  es: 'Español',
  fr: 'Français',
};

/**
 * `<LocaleSwitcher />` — Radix Select styled (shadcn) que dispara POST
 * para `endpoint` com `{locale}`, aproveitando Inertia router para
 * preservar state e re-renderizar com o novo locale resolvido.
 */
export function LocaleSwitcher({
  endpoint = '/admin/locale',
  label,
  labels,
  className,
}: LocaleSwitcherProps): ReactElement {
  const { locale, available, t } = useI18nContext();
  const selectId = useId();
  const fieldLabel = label ?? t('arqel.locale.switch');
  const map = { ...DEFAULT_LABELS, ...(labels ?? {}) };

  // After a client-side locale change the Inertia visit swaps the page
  // component without re-rendering the Blade shell that set `<html lang>`,
  // leaving the attribute stale (screen readers keep the old pronunciation).
  // Sync it from the active locale, normalizing the underscore Laravel tag
  // (`pt_BR`) to BCP-47 (`pt-BR`) so `lang` is a valid language tag.
  useEffect(() => {
    if (typeof document === 'undefined') {
      return;
    }
    document.documentElement.lang = locale.replace(/_/g, '-');
  }, [locale]);

  const handleValueChange = (next: string): void => {
    if (next === locale) {
      return;
    }
    router.post(endpoint, { locale: next }, { preserveScroll: true, preserveState: true });
  };

  return (
    <div className={className} data-arqel-locale-switcher="">
      <Label htmlFor={selectId} className="text-xs text-muted-foreground mb-1 block">
        {fieldLabel}
      </Label>
      <Select value={locale} onValueChange={handleValueChange}>
        <SelectTrigger id={selectId} aria-label={fieldLabel}>
          <SelectValue placeholder={fieldLabel} />
        </SelectTrigger>
        <SelectContent>
          {available.map((code) => (
            <SelectItem key={code} value={code}>
              {map[code] ?? code}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

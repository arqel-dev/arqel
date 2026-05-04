import { router } from '@inertiajs/react';
import { type ChangeEvent, useId } from 'react';
import { useI18nContext } from './I18nProvider';

type LocaleSwitcherProps = {
  /** Endpoint POST que persiste o locale na sessão (default `/admin/locale`). */
  endpoint?: string;
  /** Label visível acima do select (default usa `t('locale.switcher.label')`). */
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
 * `<LocaleSwitcher />` — `<select>` semanticamente correto que dispara
 * POST a `endpoint` com `{locale}`, aproveitando Inertia router para
 * preservar state e re-renderizar com o novo locale resolvido.
 */
export function LocaleSwitcher({
  endpoint = '/admin/locale',
  label,
  labels,
  className,
}: LocaleSwitcherProps): JSX.Element {
  const { locale, available, t } = useI18nContext();
  const selectId = useId();
  const fieldLabel = label ?? t('locale.switcher.label');
  const map = { ...DEFAULT_LABELS, ...(labels ?? {}) };

  const handleChange = (event: ChangeEvent<HTMLSelectElement>) => {
    const next = event.target.value;
    if (next === locale) {
      return;
    }
    router.post(endpoint, { locale: next }, { preserveScroll: true, preserveState: true });
  };

  return (
    <div className={className} data-arqel-locale-switcher>
      <label
        htmlFor={selectId}
        style={{
          display: 'block',
          fontSize: '0.75rem',
          color: 'var(--arqel-color-fg-muted, #525252)',
          marginBottom: '0.25rem',
        }}
      >
        {fieldLabel}
      </label>
      <select id={selectId} value={locale} onChange={handleChange} aria-label={fieldLabel}>
        {available.map((code) => (
          <option key={code} value={code}>
            {map[code] ?? code}
          </option>
        ))}
      </select>
    </div>
  );
}

import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { I18nProvider, useI18nContext } from './I18nProvider';
import { useTranslation } from './useTranslation';

const dict = {
  actions: { save: 'Save' },
};

function Probe() {
  const t = useTranslation();
  return <span data-testid="probe">{t('actions.save')}</span>;
}

describe('<I18nProvider>', () => {
  it('exposes translations via useTranslation', () => {
    render(
      <I18nProvider i18n={{ locale: 'en', available: ['en'], translations: dict }}>
        <Probe />
      </I18nProvider>,
    );
    expect(screen.getByTestId('probe').textContent).toBe('Save');
  });

  it('falls back to the original key when translation missing', () => {
    function Missing() {
      const t = useTranslation();
      return <span data-testid="missing">{t('actions.unknown')}</span>;
    }
    render(
      <I18nProvider i18n={{ locale: 'en', available: ['en'], translations: {} }}>
        <Missing />
      </I18nProvider>,
    );
    expect(screen.getByTestId('missing').textContent).toBe('actions.unknown');
  });

  it('pluralizes :count strings like useArqelTranslations (delegates to the same core)', () => {
    const pluralDict = {
      table: { selected: '{one} :count item|{other} :count items' },
    };
    function PluralProbe({ count }: { count: number }) {
      const t = useTranslation();
      return <span data-testid="plural">{t('table.selected', { count })}</span>;
    }
    const { rerender } = render(
      <I18nProvider i18n={{ locale: 'en', available: ['en'], translations: pluralDict }}>
        <PluralProbe count={1} />
      </I18nProvider>,
    );
    // Singular form selected via Intl.PluralRules — the divergent legacy
    // translator returned the raw multi-form string instead.
    expect(screen.getByTestId('plural').textContent).toBe('1 item');

    rerender(
      <I18nProvider i18n={{ locale: 'en', available: ['en'], translations: pluralDict }}>
        <PluralProbe count={3} />
      </I18nProvider>,
    );
    expect(screen.getByTestId('plural').textContent).toBe('3 items');
  });

  it('uses fallbackLocale when no shared props are present', () => {
    function LocaleDump() {
      const ctx = useI18nContext();
      return <span data-testid="locale">{ctx.locale}</span>;
    }
    render(
      <I18nProvider fallbackLocale="pt_BR">
        <LocaleDump />
      </I18nProvider>,
    );
    expect(screen.getByTestId('locale').textContent).toBe('pt_BR');
  });
});

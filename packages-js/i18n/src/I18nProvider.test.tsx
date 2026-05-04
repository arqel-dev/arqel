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

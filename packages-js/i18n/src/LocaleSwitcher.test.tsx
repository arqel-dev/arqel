import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
  router: { post: postMock },
  usePage: () => ({ props: {} }),
}));

import { I18nProvider } from './I18nProvider';
import { LocaleSwitcher } from './LocaleSwitcher';

// The framework ships the switcher label under `arqel.locale.switch`
// (packages/core/resources/lang/{locale}/arqel.php). The component must read
// that key so a real translation payload resolves instead of leaking the raw
// key as the visible label.
const baseProps = {
  locale: 'en',
  available: ['en', 'pt_BR'] as const,
  translations: { arqel: { locale: { switch: 'Language' } } },
};

describe('<LocaleSwitcher>', () => {
  it('renders the translated label from arqel.locale.switch', () => {
    render(
      <I18nProvider i18n={baseProps}>
        <LocaleSwitcher />
      </I18nProvider>,
    );
    // The label appears on the <label> and as the trigger's aria-label.
    expect(screen.getByText('Language')).toBeInTheDocument();
    // The raw key must NOT leak into the UI.
    expect(screen.queryByText('locale.switcher.label')).toBeNull();
    expect(screen.queryByText('arqel.locale.switch')).toBeNull();
  });

  it('syncs document.documentElement.lang to the BCP-47 active locale', () => {
    document.documentElement.lang = 'en';
    render(
      <I18nProvider
        i18n={{ ...baseProps, locale: 'pt_BR', available: ['en', 'pt_BR'] }}
      >
        <LocaleSwitcher />
      </I18nProvider>,
    );
    // Underscore Laravel tag is normalized to a valid BCP-47 language tag.
    expect(document.documentElement.lang).toBe('pt-BR');
  });

  it('honours an explicit label prop over the translation', () => {
    render(
      <I18nProvider i18n={baseProps}>
        <LocaleSwitcher label="Idioma" />
      </I18nProvider>,
    );
    expect(screen.getByText('Idioma')).toBeInTheDocument();
  });

  it('renders a trigger labelled by the resolved label', () => {
    render(
      <I18nProvider i18n={baseProps}>
        <LocaleSwitcher />
      </I18nProvider>,
    );
    expect(screen.getByRole('combobox', { name: 'Language' })).toBeInTheDocument();
  });
});

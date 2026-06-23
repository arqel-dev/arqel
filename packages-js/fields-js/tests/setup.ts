import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

interface MockPageState {
  props: Record<string, unknown>;
}

const mockPage: MockPageState = { props: {} };

/**
 * Install a translation dictionary into the mocked Inertia `i18n` prop so the
 * field renderers' `useArqelTranslations()` / `useArqelLocale()` resolve
 * against it (mirroring how `HandleArqelInertiaRequests` shares
 * `props.i18n.translations`). Pass a nested object, e.g.
 * `{ arqel: { fields: { increment: 'Incrementar' } } }`.
 */
export function setMockTranslations(translations: Record<string, unknown>, locale = 'pt_BR'): void {
  mockPage.props = { i18n: { locale, translations } };
}

export function resetMockPage(): void {
  mockPage.props = {};
}

// `useArqelTranslations` / `useArqelLocale` call Inertia's `usePage()`. Mock it
// so the field components can resolve their own a11y/placeholder strings under
// jsdom without a full Inertia app shell. With no dictionary installed the
// translate helper returns each component's English fallback, so a11y names
// stay stable for tests that don't opt into a locale.
vi.mock('@inertiajs/react', () => ({
  usePage: () => mockPage,
}));

afterEach(() => {
  cleanup();
  resetMockPage();
});

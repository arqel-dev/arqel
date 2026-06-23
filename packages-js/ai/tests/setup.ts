import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

interface MockPageState {
  props: Record<string, unknown>;
}

const mockPage: MockPageState = {
  props: {},
};

/**
 * Install a translation dictionary into the mocked Inertia `i18n` prop so
 * `useArqelTranslations()` resolves keys against it (mirroring how
 * `HandleArqelInertiaRequests` shares `props.i18n.translations`). Pass a
 * nested object (e.g. `{ arqel: { ai: { generate: 'Gerar com IA' } } }`).
 */
export function setMockTranslations(translations: Record<string, unknown>, locale = 'pt_BR'): void {
  mockPage.props = { i18n: { locale, translations } };
}

export function resetMockTranslations(): void {
  mockPage.props = {};
}

vi.mock('@inertiajs/react', () => ({
  usePage: () => mockPage,
}));

afterEach(() => {
  cleanup();
  resetMockTranslations();
});

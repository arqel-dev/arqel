import { vi } from 'vitest';

type Dict = Record<string, unknown>;

const mockState: { translations: Dict } = { translations: {} };

function lookup(dict: Dict, key: string): string | undefined {
  let node: unknown = dict;
  for (const segment of key.split('.')) {
    if (node !== null && typeof node === 'object' && segment in (node as Dict)) {
      node = (node as Dict)[segment];
    } else {
      return undefined;
    }
  }
  return typeof node === 'string' ? node : undefined;
}

/**
 * Install a translation dictionary the mocked `useArqelTranslations()` resolves
 * against (mirroring `props.i18n.translations`). With no dictionary installed
 * the translate helper returns the caller's English `fallback`, so accessible
 * names stay stable.
 */
export function setMockTranslations(translations: Dict): void {
  mockState.translations = translations;
}

export function resetMockPage(): void {
  mockState.translations = {};
}

// `<ThemeToggle>` calls `useArqelTranslations()` from `@arqel-dev/react/utils`,
// which internally reads Inertia's `usePage()` and throws outside an Inertia
// app. Mock the hook directly so the toggle resolves its aria-label/title under
// jsdom without a full Inertia shell, while still honouring an installed
// dictionary + English fallback (the real hook's contract).
vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations:
    () =>
    (key: string, fallback?: string): string =>
      lookup(mockState.translations, key) ?? fallback ?? key,
}));

import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, type Mock, vi } from 'vitest';

interface MockFormState {
  data: Record<string, unknown>;
  errors: Record<string, string>;
  processing: boolean;
}

interface MockPageState {
  props: Record<string, unknown>;
}

const mockState: MockFormState = {
  data: {},
  errors: {},
  processing: false,
};

const mockPage: MockPageState = {
  props: { flash: {} },
};

// Explicit type: Vitest 4's inferred `vi.fn()` type references an internal
// @vitest/spy path that isn't portable across the package boundary (TS2742).
export const postSpy: Mock = vi.fn();

export function setMockErrors(errors: Record<string, string>): void {
  mockState.errors = errors;
}

export function setMockPageProps(props: Record<string, unknown>): void {
  mockPage.props = props;
}

/**
 * Install a translation dictionary into the mocked Inertia `i18n` prop so
 * `useArqelTranslations()` resolves keys against it (mirroring how
 * `HandleArqelInertiaRequests` shares `props.i18n.translations`). Pass a
 * nested object (e.g. `{ arqel: { auth: { login_title: 'Bem-vindo' } } }`).
 */
export function setMockTranslations(
  translations: Record<string, unknown>,
  locale = 'pt_BR',
): void {
  mockPage.props = { flash: {}, i18n: { locale, translations } };
}

export function resetMockForm(): void {
  mockState.data = {};
  mockState.errors = {};
  mockState.processing = false;
  mockPage.props = { flash: {} };
  postSpy.mockReset();
}

vi.mock('@inertiajs/react', () => ({
  useForm: <T extends Record<string, unknown>>(initial: T) => {
    mockState.data = { ...initial };
    return {
      get data() {
        return mockState.data as T;
      },
      setData: (key: keyof T, value: unknown) => {
        mockState.data[key as string] = value;
      },
      post: postSpy,
      processing: mockState.processing,
      get errors() {
        return mockState.errors;
      },
      reset: vi.fn(),
    };
  },
  usePage: () => mockPage,
}));

afterEach(() => {
  cleanup();
  resetMockForm();
});

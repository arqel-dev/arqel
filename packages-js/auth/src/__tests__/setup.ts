import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

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

export const postSpy = vi.fn();

export function setMockErrors(errors: Record<string, string>): void {
  mockState.errors = errors;
}

export function setMockPageProps(props: Record<string, unknown>): void {
  mockPage.props = props;
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

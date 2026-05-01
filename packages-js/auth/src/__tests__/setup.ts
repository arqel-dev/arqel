import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

interface MockFormState {
  data: Record<string, unknown>;
  errors: Record<string, string>;
  processing: boolean;
}

const mockState: MockFormState = {
  data: {},
  errors: {},
  processing: false,
};

export const postSpy = vi.fn();

export function setMockErrors(errors: Record<string, string>): void {
  mockState.errors = errors;
}

export function resetMockForm(): void {
  mockState.data = {};
  mockState.errors = {};
  mockState.processing = false;
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
}));

afterEach(() => {
  cleanup();
  resetMockForm();
});

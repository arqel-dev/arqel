import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

interface FakePage {
  props: Record<string, unknown>;
  url?: string;
  component?: string;
}

let currentPage: FakePage = { props: {} };

export function setMockPage(page: FakePage): void {
  currentPage = page;
}

export function resetMockPage(): void {
  currentPage = { props: {} };
}

vi.mock('@inertiajs/react', async () => {
  const actual = await vi.importActual<typeof import('@inertiajs/react')>('@inertiajs/react');
  return {
    ...actual,
    usePage: () => currentPage,
    router: {
      visit: vi.fn(),
      get: vi.fn(),
      reload: vi.fn(),
    },
  };
});

afterEach(() => {
  cleanup();
});

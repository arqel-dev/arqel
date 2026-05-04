import '@testing-library/jest-dom/vitest';
import { vi } from 'vitest';

// Default mock for `@inertiajs/react` so `usePage()` does not throw in
// jsdom. Individual test files may override `router.post` etc. via
// `vi.mock()` when needed.
vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: {} }),
  router: { post: vi.fn(), visit: vi.fn() },
}));

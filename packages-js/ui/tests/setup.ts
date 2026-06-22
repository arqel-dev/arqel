import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

// Framework UI primitives translate their chrome via `useArqelTranslations`,
// which reads `usePage().props.i18n`. Default `usePage` to an empty props bag so
// any component rendered in a test works outside an Inertia app — the empty bag
// exercises each component's English fallback. Tests that assert a specific
// translation override this with their own `vi.mock('@inertiajs/react', …)`.
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: () => ({ props: {} }) };
});

afterEach(() => {
  cleanup();
});

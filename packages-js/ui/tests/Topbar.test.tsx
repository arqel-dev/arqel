import { ThemeProvider } from '@arqel-dev/react/providers';
import { render, screen } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { SidebarProvider } from '../src/shadcn/ui/sidebar.js';
import { Topbar } from '../src/shell/Topbar.js';

// The shadcn sidebar block calls window.matchMedia (use-mobile); jsdom lacks it.
beforeAll(() => {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string) => ({
      matches: false,
      media: query,
      onchange: null,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    }),
  });
});

const { pageMock } = vi.hoisted(() => ({
  pageMock: vi.fn(() => ({ props: {} as Record<string, unknown> })),
}));
vi.mock('@inertiajs/react', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@inertiajs/react')>();
  return { ...actual, usePage: pageMock };
});

// `<Topbar>` reads sidebar state via `<SidebarTrigger>`, so renders must be
// wrapped in both `SidebarProvider` (sidebar context) and `ThemeProvider`
// (theme context consumed by the user-menu slot).
function renderTopbar(node: Parameters<typeof Topbar>[0]) {
  return render(
    <ThemeProvider defaultTheme="light">
      <SidebarProvider>
        <Topbar {...node} />
      </SidebarProvider>
    </ThemeProvider>,
  );
}

describe('Topbar', () => {
  it('renders the sidebar trigger and the provided slots', () => {
    renderTopbar({
      brand: <span>Acme</span>,
      userMenu: <button type="button">account</button>,
      tenantSwitcher: <span>tenant</span>,
    });

    expect(screen.getByRole('button', { name: /toggle sidebar/i })).toBeInTheDocument();
    expect(screen.getByText('Acme')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'account' })).toBeInTheDocument();
    expect(screen.getByText('tenant')).toBeInTheDocument();
  });
});

import type { NavigationItemPayload } from '@arqel-dev/hooks';
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { SidebarProvider } from '../src/shadcn/ui/sidebar.js';
import { Sidebar } from '../src/shell/Sidebar.js';

// Sidebar reads navigation from Inertia shared props via useNavigation(). When an
// explicit `items` prop is passed the shared props are unused, but usePage() is
// still called, so stub it to an empty panel.
vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ props: { panel: { navigation: [] } }, url: '/admin' }),
}));

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

function renderSidebar(items: NavigationItemPayload[], brand?: ReactNode) {
  return render(
    <SidebarProvider>
      <Sidebar items={items} brand={brand} />
    </SidebarProvider>,
  );
}

describe('Sidebar', () => {
  it('renders a lucide icon for items that declare item.icon', () => {
    const { container } = renderSidebar([
      { label: 'Posts', url: '/admin/posts', icon: 'file-text' },
    ]);

    expect(screen.getByText('Posts')).toBeInTheDocument();
    // lucide-react renders an <svg> tagged with the kebab-case icon name.
    const svg = container.querySelector('svg.lucide-file-text');
    expect(svg).not.toBeNull();
  });

  it('renders items without an icon without crashing', () => {
    const { container } = renderSidebar([{ label: 'Roles', url: '/admin/roles' }]);

    expect(screen.getByText('Roles')).toBeInTheDocument();
    expect(container.querySelector('svg.lucide')).toBeNull();
  });

  it('keeps grouping, active highlight and the brand slot', () => {
    renderSidebar(
      [
        { label: 'Dashboard', url: '/admin', icon: 'home', active: true },
        { label: 'Users', url: '/admin/users', group: 'Team', icon: 'users' },
      ],
      <span>Acme</span>,
    );

    expect(screen.getByText('Acme')).toBeInTheDocument();
    expect(screen.getByText('Team')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Dashboard/ })).toHaveAttribute('data-active', 'true');
  });
});

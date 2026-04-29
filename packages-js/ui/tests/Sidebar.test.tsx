import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Sidebar } from '../src/shell/Sidebar.js';

const items = [
  { label: 'Dashboard', url: '/admin', active: true },
  { label: 'Users', url: '/admin/users', group: 'Team', badge: 4 },
  { label: 'Roles', url: '/admin/roles', group: 'Team' },
];

describe('Sidebar', () => {
  it('renders explicit items grouped with active highlight + badge', () => {
    render(<Sidebar items={items} brand={<span>Acme</span>} />);
    expect(screen.getByText('Acme')).toBeInTheDocument();
    expect(screen.getByText('Team')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByText('4')).toBeInTheDocument();
  });

  it('returns desktop-only when no open prop is passed', () => {
    const { container } = render(<Sidebar items={items} />);
    // No Dialog overlay rendered
    expect(container.querySelector('[role="dialog"]')).toBeNull();
  });
});

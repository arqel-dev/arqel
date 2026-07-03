import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { post: (...args: unknown[]) => post(...args) },
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

const setTheme = vi.fn();
vi.mock('@arqel-dev/react/providers', () => ({
  useTheme: () => ({ theme: 'system', setTheme }),
}));

vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations: () => (_key: string, fallback?: string) => fallback ?? _key,
}));

import { UserMenu } from '../src/shell/UserMenu.js';

describe('UserMenu', () => {
  it('posts to logoutUrl when Log out is chosen', async () => {
    render(<UserMenu user={{ name: 'Ada', email: 'ada@x.com' }} logoutUrl="/admin/logout" />);
    await userEvent.click(screen.getByRole('button', { name: /open user menu/i }));
    await userEvent.click(screen.getByText(/log out/i));
    expect(post).toHaveBeenCalledWith('/admin/logout');
  });

  it('shows Profile only when profileUrl is present', async () => {
    const { rerender } = render(
      <UserMenu user={{ name: 'Ada' }} logoutUrl="/admin/logout" />,
    );
    await userEvent.click(screen.getByRole('button', { name: /open user menu/i }));
    expect(screen.queryByText(/^profile$/i)).toBeNull();

    rerender(
      <UserMenu user={{ name: 'Ada' }} logoutUrl="/admin/logout" profileUrl="/admin/profile" />,
    );
    expect(screen.getByText(/^profile$/i)).toBeInTheDocument();
  });

  it('falls back to email, then Account, when name is null', async () => {
    render(<UserMenu user={{ name: null, email: 'ada@x.com' }} logoutUrl="/admin/logout" />);
    expect(screen.getByText('ada@x.com')).toBeInTheDocument();
  });

  it('calls setTheme when a theme radio item is chosen', async () => {
    render(<UserMenu user={{ name: 'Ada' }} logoutUrl="/admin/logout" />);
    await userEvent.click(screen.getByRole('button', { name: /open user menu/i }));
    await userEvent.click(screen.getByText(/^dark$/i));
    expect(setTheme).toHaveBeenCalledWith('dark');
  });
});

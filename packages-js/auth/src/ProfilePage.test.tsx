import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

const put = vi.fn();
const setData = vi.fn();
vi.mock('@inertiajs/react', () => ({
  useForm: (initial: Record<string, unknown>) => ({
    data: initial,
    setData,
    put,
    processing: false,
    errors: {},
    reset: vi.fn(),
  }),
}));

vi.mock('@arqel-dev/react/utils', () => ({
  useArqelTranslations: () => (_key: string, fallback?: string) => fallback ?? _key,
}));

import { ProfilePage } from './ProfilePage';

describe('ProfilePage', () => {
  it('renders both the account and password sections', () => {
    render(<ProfilePage user={{ id: 1, name: 'Ada', email: 'ada@x.com' }} />);
    expect(screen.getByText(/account data/i)).toBeInTheDocument();
    expect(screen.getByText(/change password/i)).toBeInTheDocument();
  });

  it('submits the account form to updateUrl via put', async () => {
    render(
      <ProfilePage user={{ id: 1, name: 'Ada', email: 'ada@x.com' }} updateUrl="/admin/profile" />,
    );
    await userEvent.click(screen.getByRole('button', { name: /^save$/i }));
    expect(put).toHaveBeenCalledWith('/admin/profile', expect.any(Object));
  });

  it('submits the password form to passwordUrl via put', async () => {
    render(
      <ProfilePage
        user={{ id: 1, name: 'Ada', email: 'ada@x.com' }}
        passwordUrl="/admin/profile/password"
      />,
    );
    await userEvent.click(screen.getByRole('button', { name: /change password/i }));
    expect(put).toHaveBeenCalledWith('/admin/profile/password', expect.any(Object));
  });
});

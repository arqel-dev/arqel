import { ThemeProvider } from '@arqel-dev/react/providers';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { Topbar } from '../src/shell/Topbar.js';

function withTheme(node: ReactNode) {
  return <ThemeProvider defaultTheme="light">{node}</ThemeProvider>;
}

describe('Topbar', () => {
  it('toggles theme via the theme button', async () => {
    const user = userEvent.setup();
    render(withTheme(<Topbar />));

    const root = document.documentElement;
    expect(root.classList.contains('dark')).toBe(false);

    const toggle = screen.getByRole('button', { name: /switch to dark theme/i });
    await user.click(toggle);
    expect(root.classList.contains('dark')).toBe(true);
  });

  it('fires onMobileMenuClick when the menu trigger is clicked', async () => {
    const user = userEvent.setup();
    const onMobileMenuClick = vi.fn();
    render(withTheme(<Topbar onMobileMenuClick={onMobileMenuClick} />));

    await user.click(screen.getByRole('button', { name: /open navigation/i }));
    expect(onMobileMenuClick).toHaveBeenCalledTimes(1);
  });
});

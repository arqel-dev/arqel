import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ThemeProvider } from '../ThemeProvider';
import { ThemeToggle } from '../ThemeToggle';

import { installMatchMedia } from './matchMedia.helper';

describe('ThemeToggle', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('dark');
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
  });

  it('renderiza com aria-label do tema atual (system default)', () => {
    installMatchMedia(false);
    render(
      <ThemeProvider>
        <ThemeToggle />
      </ThemeProvider>,
    );
    const button = screen.getByRole('button');
    expect(button.getAttribute('aria-label')).toMatch(/sistema/);
    expect(button.getAttribute('data-theme-current')).toBe('system');
  });

  it('cicla system -> light -> dark -> system ao clicar', () => {
    installMatchMedia(false);
    render(
      <ThemeProvider>
        <ThemeToggle />
      </ThemeProvider>,
    );
    const button = screen.getByRole('button');
    expect(button.getAttribute('data-theme-current')).toBe('system');

    fireEvent.click(button);
    expect(button.getAttribute('data-theme-current')).toBe('light');

    fireEvent.click(button);
    expect(button.getAttribute('data-theme-current')).toBe('dark');

    fireEvent.click(button);
    expect(button.getAttribute('data-theme-current')).toBe('system');
  });

  it('aplica className passada via prop', () => {
    installMatchMedia(false);
    render(
      <ThemeProvider>
        <ThemeToggle className="my-toggle" />
      </ThemeProvider>,
    );
    expect(screen.getByRole('button').className).toContain('my-toggle');
  });

  it('renderiza ícone SVG apropriado para cada estado', () => {
    installMatchMedia(false);
    const { container } = render(
      <ThemeProvider defaultTheme="light">
        <ThemeToggle />
      </ThemeProvider>,
    );
    expect(container.querySelector('svg')).not.toBeNull();
  });
});

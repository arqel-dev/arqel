import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ThemeProvider } from '../ThemeProvider';
import { ThemeToggle } from '../ThemeToggle';

import { installMatchMedia } from './matchMedia.helper';
import { resetMockPage, setMockTranslations } from './inertia.setup';

describe('ThemeToggle', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('dark');
    resetMockPage();
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
  });

  it('renderiza com aria-label do tema atual em inglês (fallback, system default)', () => {
    installMatchMedia(false);
    render(
      <ThemeProvider>
        <ThemeToggle />
      </ThemeProvider>,
    );
    const button = screen.getByRole('button');
    expect(button.getAttribute('aria-label')).toBe('Theme: system (click for light)');
    expect(button.getAttribute('title')).toBe('Theme: system (click for light)');
    expect(button.getAttribute('data-theme-current')).toBe('system');
  });

  it('localiza aria-label/title via chaves arqel.theme.toggle.*', () => {
    installMatchMedia(false);
    setMockTranslations({
      arqel: {
        theme: {
          toggle: {
            system: 'Tema: sistema (clique para claro)',
            light: 'Tema: claro (clique para escuro)',
            dark: 'Tema: escuro (clique para sistema)',
          },
        },
      },
    });
    render(
      <ThemeProvider>
        <ThemeToggle />
      </ThemeProvider>,
    );
    const button = screen.getByRole('button');
    expect(button.getAttribute('aria-label')).toBe('Tema: sistema (clique para claro)');
    expect(button.getAttribute('title')).toBe('Tema: sistema (clique para claro)');

    fireEvent.click(button);
    expect(button.getAttribute('aria-label')).toBe('Tema: claro (clique para escuro)');
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

import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { AppShell } from '../src/shell/AppShell.js';

describe('AppShell', () => {
  it('renders the sidebar-left variant by default with sidebar before content', () => {
    const { container } = render(
      <AppShell sidebar={<aside data-testid="sb" />} topbar={<header data-testid="tb" />}>
        <div data-testid="main">main</div>
      </AppShell>,
    );
    const root = container.querySelector('[data-arqel-shell]');
    expect(root?.getAttribute('data-arqel-shell')).toBe('sidebar-left');
    expect(root?.getAttribute('data-sidebar-side')).toBe('left');
    expect(screen.getByTestId('sb')).toBeInTheDocument();
    expect(screen.getByTestId('tb')).toBeInTheDocument();
  });

  it('reverses flex direction on sidebar-right', () => {
    const { container } = render(
      <AppShell variant="sidebar-right" sidebar={<aside />}>
        <div>x</div>
      </AppShell>,
    );
    const root = container.querySelector('[data-arqel-shell]');
    expect(root?.getAttribute('data-sidebar-side')).toBe('right');
    expect(root?.className).toMatch(/flex-row-reverse/);
  });

  it('omits the sidebar slot in topbar-only', () => {
    const { container } = render(
      <AppShell variant="topbar-only" topbar={<header data-testid="tb" />}>
        <div>x</div>
      </AppShell>,
    );
    expect(container.querySelector('[data-arqel-shell]')?.getAttribute('data-arqel-shell')).toBe(
      'topbar-only',
    );
    expect(screen.getByTestId('tb')).toBeInTheDocument();
    expect(container.querySelector('aside')).toBeNull();
  });

  it('renders bare children in full-width', () => {
    const { container } = render(
      <AppShell variant="full-width" topbar={<header data-testid="tb" />}>
        <div data-testid="main">x</div>
      </AppShell>,
    );
    expect(container.querySelector('[data-arqel-shell]')?.getAttribute('data-arqel-shell')).toBe(
      'full-width',
    );
    expect(screen.queryByTestId('tb')).toBeNull();
    expect(screen.getByTestId('main')).toBeInTheDocument();
  });
});

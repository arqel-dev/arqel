import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { MainContent } from '../src/shell/MainContent.js';

describe('MainContent', () => {
  it('renders breadcrumbs and header slots when provided', () => {
    render(
      <MainContent
        breadcrumbs={<nav aria-label="breadcrumb" data-testid="crumbs" />}
        header={<h1 data-testid="header">Users</h1>}
      >
        <div data-testid="body">body</div>
      </MainContent>,
    );

    expect(screen.getByTestId('crumbs')).toBeInTheDocument();
    expect(screen.getByTestId('header')).toBeInTheDocument();
    expect(screen.getByTestId('body')).toBeInTheDocument();
  });

  it('applies the default 7xl max width', () => {
    const { container } = render(
      <MainContent>
        <div>x</div>
      </MainContent>,
    );
    expect(container.querySelector('[data-arqel-main]')?.className).toMatch(/max-w-7xl/);
  });

  it('drops max-width when set to none', () => {
    const { container } = render(
      <MainContent maxWidth="none">
        <div>x</div>
      </MainContent>,
    );
    expect(container.querySelector('[data-arqel-main]')?.className).not.toMatch(/max-w-/);
  });
});

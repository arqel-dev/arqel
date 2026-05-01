import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { App } from '../App';

describe('<App />', () => {
  it('renders inactive state by default', () => {
    render(<App />);
    const root = screen.getByTestId('arqel-devtools-panel');
    expect(root).toHaveAttribute('data-status', 'inactive');
    expect(screen.getByRole('heading')).toHaveTextContent('Arqel DevTools — Inactive');
  });

  it('renders connected state when a version is provided', () => {
    render(<App version="0.10.0" />);
    const root = screen.getByTestId('arqel-devtools-panel');
    expect(root).toHaveAttribute('data-status', 'connected');
    expect(screen.getByRole('heading')).toHaveTextContent('Arqel DevTools — Connected (v0.10.0)');
  });

  it('treats empty version strings as inactive', () => {
    render(<App version="" />);
    expect(screen.getByTestId('arqel-devtools-panel')).toHaveAttribute('data-status', 'inactive');
  });
});

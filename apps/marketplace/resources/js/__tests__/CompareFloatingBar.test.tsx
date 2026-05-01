import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { CompareFloatingBar } from '../Components/Marketplace/CompareFloatingBar';

const KEY = 'arqel:compare:slugs';

describe('<CompareFloatingBar />', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  afterEach(() => {
    window.localStorage.clear();
  });

  it('is hidden when there are no slugs', () => {
    const { container } = render(<CompareFloatingBar />);
    expect(container.firstChild).toBeNull();
  });

  it('renders chips and clear button works', () => {
    window.localStorage.setItem(KEY, JSON.stringify(['foo', 'bar']));
    render(<CompareFloatingBar />);
    expect(screen.getByTestId('compare-floating-bar')).toBeInTheDocument();
    expect(screen.getByTestId('compare-chip-foo')).toBeInTheDocument();
    expect(screen.getByTestId('compare-chip-bar')).toBeInTheDocument();
    expect(screen.getByTestId('compare-now')).toHaveAttribute('href', '/compare?slugs=foo,bar');
    act(() => {
      fireEvent.click(screen.getByTestId('compare-clear'));
    });
    expect(window.localStorage.getItem(KEY)).toBe('[]');
  });
});

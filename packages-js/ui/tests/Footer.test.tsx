import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Footer } from '../src/shell/Footer.js';

describe('Footer', () => {
  it('renders children inside <footer>', () => {
    const { container } = render(<Footer>© 2026</Footer>);
    expect(container.querySelector('footer')).toBeInTheDocument();
    expect(screen.getByText('© 2026')).toBeInTheDocument();
  });
});

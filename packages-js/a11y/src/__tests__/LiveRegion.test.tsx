import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { LiveRegion } from '../LiveRegion';

describe('LiveRegion', () => {
  it('defaults to aria-live polite and role=status', () => {
    render(<LiveRegion message="ok" id="lr1" />);
    const region = document.getElementById('lr1');
    expect(region?.getAttribute('aria-live')).toBe('polite');
    expect(region?.getAttribute('role')).toBe('status');
    expect(region?.textContent).toBe('ok');
  });

  it('uses role=alert when assertive', () => {
    render(<LiveRegion message="bad" priority="assertive" id="lr2" />);
    const region = document.getElementById('lr2');
    expect(region?.getAttribute('aria-live')).toBe('assertive');
    expect(region?.getAttribute('role')).toBe('alert');
  });

  it('renders message content for screen readers', () => {
    render(<LiveRegion message="updated" />);
    expect(screen.getByText('updated')).toBeInTheDocument();
  });
});

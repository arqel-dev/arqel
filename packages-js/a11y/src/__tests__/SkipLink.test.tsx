import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SkipLink } from '../SkipLink';

describe('SkipLink', () => {
  it('renders an anchor pointing to target id', () => {
    render(<SkipLink targetId="main" />);
    const link = screen.getByRole('link');
    expect(link.getAttribute('href')).toBe('#main');
    expect(link.textContent).toBe('Pular para o conteúdo principal');
  });

  it('applies focused style when focused', () => {
    render(<SkipLink targetId="main" />);
    const link = screen.getByRole('link') as HTMLAnchorElement;
    fireEvent.focus(link);
    expect(link.style.transform).toBe('translateY(0)');
  });

  it('moves focus to target on click', () => {
    document.body.innerHTML = '<main id="main-content">content</main>';
    render(<SkipLink targetId="main-content" label="Skip" />);
    const link = screen.getByRole('link');
    fireEvent.click(link);
    expect(document.getElementById('main-content')).toBe(document.activeElement);
  });

  it('uses custom label', () => {
    render(<SkipLink targetId="main" label="Saltar para conteúdo" />);
    expect(screen.getByText('Saltar para conteúdo')).toBeInTheDocument();
  });
});

import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { SkipLink } from '../SkipLink';
import { setMockTranslations } from './setup';

describe('SkipLink', () => {
  it('renders an anchor pointing to target id (English fallback default)', () => {
    render(<SkipLink targetId="main" />);
    const link = screen.getByRole('link');
    expect(link.getAttribute('href')).toBe('#main');
    expect(link.textContent).toBe('Skip to main content');
  });

  it('localizes the default label via arqel.a11y.skip_to_content', () => {
    setMockTranslations({
      arqel: { a11y: { skip_to_content: 'Pular para o conteúdo principal' } },
    });
    render(<SkipLink targetId="main" />);
    expect(screen.getByText('Pular para o conteúdo principal')).toBeInTheDocument();
  });

  it('prefers an explicit label prop over the translated default', () => {
    setMockTranslations({
      arqel: { a11y: { skip_to_content: 'Pular para o conteúdo principal' } },
    });
    render(<SkipLink targetId="main" label="Custom skip" />);
    expect(screen.getByText('Custom skip')).toBeInTheDocument();
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

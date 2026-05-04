import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { VisuallyHidden } from '../VisuallyHidden';

describe('VisuallyHidden', () => {
  it('renders children with aria-hidden=false by default', () => {
    render(<VisuallyHidden>menu</VisuallyHidden>);
    const node = screen.getByText('menu');
    expect(node.getAttribute('aria-hidden')).toBe('false');
  });

  it('uses absolute positioning to clip content visually', () => {
    render(<VisuallyHidden>label</VisuallyHidden>);
    const node = screen.getByText('label') as HTMLElement;
    expect(node.style.position).toBe('absolute');
    expect(node.style.width).toBe('1px');
    expect(node.style.height).toBe('1px');
  });

  it('honours the `as` prop to render different tags', () => {
    render(<VisuallyHidden as="label">text</VisuallyHidden>);
    const node = screen.getByText('text');
    expect(node.tagName).toBe('LABEL');
  });
});

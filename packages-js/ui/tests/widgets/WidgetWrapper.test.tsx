import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { WidgetWrapper } from '../../src/widgets/WidgetWrapper.js';

describe('WidgetWrapper', () => {
  it('renders skeleton placeholder when loading', () => {
    const { container } = render(
      <WidgetWrapper heading="Sales" loading>
        <span>hidden</span>
      </WidgetWrapper>,
    );
    expect(container.querySelector('[data-widget-state="loading"]')).not.toBeNull();
    expect(container.querySelector('.animate-pulse')).not.toBeNull();
    expect(screen.queryByText('hidden')).toBeNull();
  });

  it('renders error message and retry button when error is set', () => {
    const onRetry = vi.fn();
    render(
      <WidgetWrapper heading="Sales" error={new Error('Boom')} onRetry={onRetry}>
        <span>hidden</span>
      </WidgetWrapper>,
    );
    const alert = screen.getByRole('alert');
    expect(alert).toHaveTextContent('Boom');
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));
    expect(onRetry).toHaveBeenCalledTimes(1);
  });

  it('omits retry button when no onRetry handler is provided', () => {
    render(
      <WidgetWrapper error={new Error('Boom')}>
        <span>hidden</span>
      </WidgetWrapper>,
    );
    expect(screen.queryByRole('button', { name: 'Retry' })).toBeNull();
  });

  it('renders children with heading + description in default state', () => {
    render(
      <WidgetWrapper heading="Sales" description="Last 30 days">
        <span>real-content</span>
      </WidgetWrapper>,
    );
    expect(screen.getByRole('heading', { level: 2, name: 'Sales' })).toBeInTheDocument();
    expect(screen.getByText('Last 30 days')).toBeInTheDocument();
    expect(screen.getByText('real-content')).toBeInTheDocument();
  });

  it('exposes aria-label sourced from heading on the section', () => {
    render(
      <WidgetWrapper heading="Revenue">
        <span>x</span>
      </WidgetWrapper>,
    );
    expect(screen.getByLabelText('Revenue').tagName).toBe('SECTION');
  });

  it('applies numeric columnSpan as col-span-{n}', () => {
    const { container } = render(
      <WidgetWrapper heading="x" columnSpan={2}>
        <span>x</span>
      </WidgetWrapper>,
    );
    expect(container.querySelector('section')?.className).toContain('col-span-2');
  });
});

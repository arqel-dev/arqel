import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FlashToast } from '../../src/flash/FlashToast.js';

describe('FlashToast download link', () => {
  afterEach(() => vi.useRealTimers());

  it('renders a download link when downloadUrl is present', () => {
    render(
      <FlashToast
        kind="success"
        message="Export ready"
        downloadUrl="/admin/exports/abc/download"
        onDismiss={() => {}}
      />,
    );
    const link = screen.getByTestId('flash-download-link');
    expect(link).toHaveAttribute('href', '/admin/exports/abc/download');
    expect(link).toHaveAttribute('download');
    expect(link).toHaveTextContent('Baixar');
  });

  it('renders no download link when downloadUrl is absent or empty', () => {
    const { unmount } = render(
      <FlashToast kind="success" message="Saved" onDismiss={() => {}} />,
    );
    expect(screen.queryByTestId('flash-download-link')).toBeNull();
    unmount();

    render(<FlashToast kind="success" message="Saved" downloadUrl="" onDismiss={() => {}} />);
    expect(screen.queryByTestId('flash-download-link')).toBeNull();
  });
});

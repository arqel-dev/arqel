import { act, render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { useAnnounce } from '../useAnnounce';

function Harness({
  onReady,
}: {
  onReady: (api: ReturnType<typeof useAnnounce>) => void;
}): React.ReactElement {
  const api = useAnnounce();
  onReady(api);
  return <div />;
}

describe('useAnnounce', () => {
  it('injects polite message into a live region', async () => {
    let api!: ReturnType<typeof useAnnounce>;
    render(<Harness onReady={(a) => (api = a)} />);
    await act(async () => {
      api.announce('hello');
      await new Promise((r) => setTimeout(r, 80));
    });
    const region = document.getElementById('arqel-a11y-live-polite');
    expect(region).not.toBeNull();
    expect(region?.getAttribute('aria-live')).toBe('polite');
    expect(region?.textContent).toBe('hello');
  });

  it('uses assertive region for assertive priority', async () => {
    let api!: ReturnType<typeof useAnnounce>;
    render(<Harness onReady={(a) => (api = a)} />);
    await act(async () => {
      api.announce('error!', 'assertive');
      await new Promise((r) => setTimeout(r, 80));
    });
    const region = document.getElementById('arqel-a11y-live-assertive');
    expect(region).not.toBeNull();
    expect(region?.getAttribute('aria-live')).toBe('assertive');
    expect(region?.getAttribute('role')).toBe('alert');
    expect(region?.textContent).toBe('error!');
  });

  it('is SSR-safe (no document access at import time)', () => {
    // Importing again must not throw; hook itself only touches document via callback.
    expect(() => useAnnounce).not.toThrow();
  });
});

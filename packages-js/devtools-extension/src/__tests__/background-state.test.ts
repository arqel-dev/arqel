import { describe, expect, it, vi } from 'vitest';

import { type ArqelDetectedMessage, createBackground } from '../background';

const msg = (detected: boolean, version: string | null): ArqelDetectedMessage => ({
  type: 'arqel.detected',
  detected,
  version,
});

const ICON_PATHS = {
  '16': 'icons/icon-16.png',
  '32': 'icons/icon-32.png',
  '48': 'icons/icon-48.png',
  '128': 'icons/icon-128.png',
} as const;

describe('createBackground', () => {
  it('stores per-tab detection state when a tab reports active', () => {
    const setIcon = vi.fn();
    const bg = createBackground({
      setIcon,
      activeIconPaths: ICON_PATHS,
      inactiveIconPaths: ICON_PATHS,
    });

    const response = bg.handleMessage(msg(true, '0.10.0'), { tab: { id: 42 } });

    expect(response).toEqual({ ok: true, type: 'arqel.detected' });
    expect(bg.getTabState(42)).toEqual({ detected: true, version: '0.10.0' });
    expect(setIcon).toHaveBeenCalledWith({ tabId: 42, path: ICON_PATHS });
  });

  it('keeps state segregated across tabs', () => {
    const bg = createBackground({ setIcon: vi.fn() });
    bg.handleMessage(msg(true, '1.0.0'), { tab: { id: 1 } });
    bg.handleMessage(msg(false, null), { tab: { id: 2 } });

    expect(bg.getTabState(1)).toEqual({ detected: true, version: '1.0.0' });
    expect(bg.getTabState(2)).toEqual({ detected: false, version: null });
  });

  it('drops state when the tab is removed', () => {
    const bg = createBackground({ setIcon: vi.fn() });
    bg.handleMessage(msg(true, '1.0.0'), { tab: { id: 7 } });
    expect(bg.getTabState(7)).toBeDefined();
    bg.removeTab(7);
    expect(bg.getTabState(7)).toBeUndefined();
  });

  it('uses the inactive icon set when a tab reports no Arqel runtime', () => {
    const setIcon = vi.fn();
    const active = { ...ICON_PATHS, '16': 'icons/active-16.png' };
    const inactive = { ...ICON_PATHS, '16': 'icons/inactive-16.png' };
    const bg = createBackground({
      setIcon,
      activeIconPaths: active,
      inactiveIconPaths: inactive,
    });

    bg.handleMessage(msg(false, null), { tab: { id: 99 } });

    expect(setIcon).toHaveBeenCalledWith({ tabId: 99, path: inactive });
  });

  it('ignores messages without a tabId (e.g. devtools panel pings)', () => {
    const setIcon = vi.fn();
    const bg = createBackground({ setIcon });
    bg.handleMessage(msg(true, '1.0.0'));
    expect(setIcon).not.toHaveBeenCalled();
    expect(Array.from(bg.state.keys())).toHaveLength(0);
  });
});

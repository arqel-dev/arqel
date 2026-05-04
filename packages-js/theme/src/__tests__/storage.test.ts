import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import {
  DEFAULT_STORAGE_KEY,
  getSystemTheme,
  isTheme,
  readStoredTheme,
  writeStoredTheme,
} from '../storage';

describe('storage', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('isTheme valida valores aceitos', () => {
    expect(isTheme('light')).toBe(true);
    expect(isTheme('dark')).toBe(true);
    expect(isTheme('system')).toBe(true);
    expect(isTheme('blue')).toBe(false);
    expect(isTheme(null)).toBe(false);
    expect(isTheme(42)).toBe(false);
  });

  it('readStoredTheme retorna null quando vazio', () => {
    expect(readStoredTheme()).toBeNull();
  });

  it('writeStoredTheme persiste e readStoredTheme recupera', () => {
    writeStoredTheme('dark');
    expect(window.localStorage.getItem(DEFAULT_STORAGE_KEY)).toBe('dark');
    expect(readStoredTheme()).toBe('dark');
  });

  it('readStoredTheme ignora valor inválido em localStorage', () => {
    window.localStorage.setItem(DEFAULT_STORAGE_KEY, 'rainbow');
    expect(readStoredTheme()).toBeNull();
  });

  it('readStoredTheme não lança quando localStorage falha', () => {
    const spy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    expect(readStoredTheme()).toBeNull();
    spy.mockRestore();
  });

  it('getSystemTheme retorna dark quando matchMedia bate', () => {
    const spy = vi.spyOn(window, 'matchMedia').mockReturnValue({
      matches: true,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      media: '',
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    } as unknown as MediaQueryList);
    expect(getSystemTheme()).toBe('dark');
    spy.mockRestore();
  });

  it('getSystemTheme retorna light por padrão', () => {
    const spy = vi.spyOn(window, 'matchMedia').mockReturnValue({
      matches: false,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      media: '',
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      dispatchEvent: vi.fn(),
    } as unknown as MediaQueryList);
    expect(getSystemTheme()).toBe('light');
    spy.mockRestore();
  });
});

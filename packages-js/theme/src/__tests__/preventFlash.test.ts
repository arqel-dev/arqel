import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { preventFlashScript } from '../preventFlash';

import { installMatchMedia } from './matchMedia.helper';

describe('preventFlashScript', () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove('dark');
    document.documentElement.removeAttribute('data-theme');
    document.documentElement.style.colorScheme = '';
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('retorna string com IIFE e referência ao localStorage', () => {
    const code = preventFlashScript();
    expect(code).toMatch(/^\(function\(\)/);
    expect(code).toContain('localStorage');
    expect(code).toContain('arqel-theme');
    expect(code).toContain('prefers-color-scheme: dark');
  });

  it('respeita storageKey customizado', () => {
    const code = preventFlashScript({ storageKey: 'meu-tema' });
    expect(code).toContain('meu-tema');
    expect(code).not.toContain('"arqel-theme"');
  });

  it('aplica classe dark quando localStorage=dark (executa em jsdom)', () => {
    window.localStorage.setItem('arqel-theme', 'dark');
    installMatchMedia(false);
    // Executa o IIFE no contexto atual.
    // eslint-disable-next-line no-new-func
    new Function(preventFlashScript())();
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    expect(document.documentElement.style.colorScheme).toBe('dark');
  });

  it('aplica light quando system pref é light e theme=system', () => {
    window.localStorage.setItem('arqel-theme', 'system');
    installMatchMedia(false);
    // eslint-disable-next-line no-new-func
    new Function(preventFlashScript())();
    expect(document.documentElement.classList.contains('dark')).toBe(false);
    expect(document.documentElement.style.colorScheme).toBe('light');
  });

  it('não lança quando localStorage está bloqueado', () => {
    const spy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new Error('blocked');
    });
    installMatchMedia(false);
    expect(() => {
      // eslint-disable-next-line no-new-func
      new Function(preventFlashScript())();
    }).not.toThrow();
    spy.mockRestore();
  });

  it('attribute=data-theme define atributo', () => {
    window.localStorage.setItem('arqel-theme', 'dark');
    installMatchMedia(false);
    // eslint-disable-next-line no-new-func
    new Function(preventFlashScript({ attribute: 'data-theme' }))();
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });
});

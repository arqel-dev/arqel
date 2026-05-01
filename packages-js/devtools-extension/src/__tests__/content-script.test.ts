import { afterEach, describe, expect, it } from 'vitest';
import { buildDetectMessage, detectArqel } from '../content-script';

afterEach(() => {
  delete (window as Window).__ARQEL_DEVTOOLS_HOOK__;
});

describe('detectArqel', () => {
  it('returns false when the hook is missing', () => {
    expect(detectArqel(window)).toBe(false);
  });

  it('returns true when a versioned hook is present', () => {
    window.__ARQEL_DEVTOOLS_HOOK__ = { version: '0.10.0' };
    expect(detectArqel(window)).toBe(true);
  });

  it('returns false when the hook lacks a version', () => {
    window.__ARQEL_DEVTOOLS_HOOK__ = { version: '' };
    expect(detectArqel(window)).toBe(false);
  });
});

describe('buildDetectMessage', () => {
  it('reports inactive state when no hook is present', () => {
    expect(buildDetectMessage(window)).toEqual({
      type: 'arqel.detected',
      detected: false,
      version: null,
    });
  });

  it('reports the runtime version when the hook is present', () => {
    window.__ARQEL_DEVTOOLS_HOOK__ = { version: '0.10.0' };
    expect(buildDetectMessage(window)).toEqual({
      type: 'arqel.detected',
      detected: true,
      version: '0.10.0',
    });
  });
});

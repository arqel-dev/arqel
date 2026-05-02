import { describe, expect, it } from 'vitest';
import { decodeUpdate, encodeUpdate } from '../encoders';

describe('encoders', () => {
  it('round-trips Uint8Array via base64 encode/decode', () => {
    const input = new Uint8Array([1, 2, 3, 255, 0, 128]);
    const b64 = encodeUpdate(input);
    const out = decodeUpdate(b64);
    expect(Array.from(out)).toEqual(Array.from(input));
  });

  it('produces valid base64 strings', () => {
    const input = new Uint8Array([72, 101, 108, 108, 111]); // "Hello"
    const b64 = encodeUpdate(input);
    expect(b64).toMatch(/^[A-Za-z0-9+/]+={0,2}$/);
    expect(b64).toBe('SGVsbG8=');
  });

  it('throws on malformed base64 input', () => {
    expect(() => decodeUpdate('!!!not-base64@@@')).toThrow();
  });

  it('throws on empty input', () => {
    expect(() => decodeUpdate('')).toThrow();
  });

  it('throws when encodeUpdate receives a non-Uint8Array', () => {
    // @ts-expect-error testing runtime guard
    expect(() => encodeUpdate('hello')).toThrow();
  });
});

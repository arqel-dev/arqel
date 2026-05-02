/**
 * Helpers para codificar/decodificar Yjs updates em base64.
 *
 * Yjs produz updates como `Uint8Array`. Para transitar via JSON
 * (REST snapshot persistence ou broadcast Reverb) convertemos para
 * base64. Implementação SSR-safe: detecta `Buffer` (Node) ou `btoa`
 * (browser/jsdom).
 */

const isBrowser = typeof globalThis !== 'undefined' && typeof globalThis.btoa === 'function';

export function encodeUpdate(update: Uint8Array): string {
  if (!(update instanceof Uint8Array)) {
    throw new TypeError('encodeUpdate: expected Uint8Array');
  }

  if (isBrowser) {
    let binary = '';
    for (const byte of update) {
      binary += String.fromCharCode(byte);
    }
    return globalThis.btoa(binary);
  }

  // Node fallback
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const BufferRef = (
    globalThis as { Buffer?: { from(data: Uint8Array): { toString(enc: string): string } } }
  ).Buffer;
  if (BufferRef === undefined) {
    throw new Error('encodeUpdate: no base64 encoder available');
  }
  return BufferRef.from(update).toString('base64');
}

export function decodeUpdate(s: string): Uint8Array {
  if (typeof s !== 'string' || s === '') {
    throw new TypeError('decodeUpdate: expected non-empty base64 string');
  }

  // Validate charset defensively before decoding so malformed input
  // produces a meaningful error instead of silent garbage bytes.
  if (!/^[A-Za-z0-9+/]+={0,2}$/.test(s)) {
    throw new TypeError('decodeUpdate: input is not valid base64');
  }

  if (isBrowser) {
    const binary = globalThis.atob(s);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
  }

  const BufferRef = (globalThis as { Buffer?: { from(data: string, enc: string): Uint8Array } })
    .Buffer;
  if (BufferRef === undefined) {
    throw new Error('decodeUpdate: no base64 decoder available');
  }
  return new Uint8Array(BufferRef.from(s, 'base64'));
}

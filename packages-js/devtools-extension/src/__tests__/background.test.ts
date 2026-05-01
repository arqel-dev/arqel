import { describe, expect, it } from 'vitest';
import { handleMessage } from '../background';

describe('background.handleMessage', () => {
  it('acks any incoming message with its type', () => {
    expect(handleMessage({ type: 'arqel.detect', payload: { detected: true } })).toEqual({
      ok: true,
      type: 'arqel.detect',
    });
  });

  it('handles messages without a payload', () => {
    expect(handleMessage({ type: 'arqel.ping' })).toEqual({ ok: true, type: 'arqel.ping' });
  });
});

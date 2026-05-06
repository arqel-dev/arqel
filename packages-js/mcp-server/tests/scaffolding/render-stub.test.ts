import { describe, expect, it } from 'vitest';

import { renderStub } from '../../src/scaffolding/render-stub.js';

describe('renderStub', () => {
  it('substitutes simple tokens', () => {
    const out = renderStub('Hello {{name}}!', { name: 'World' });
    expect(out).toBe('Hello World!');
  });

  it('substitutes multiple occurrences of the same token', () => {
    const out = renderStub('{{x}} and {{x}}', { x: 'y' });
    expect(out).toBe('y and y');
  });

  it('throws when template references an unknown token', () => {
    expect(() => renderStub('Hi {{missing}}', {})).toThrow(/unknown token/);
  });

  it('throws when a provided token is unused', () => {
    expect(() => renderStub('Hi {{a}}', { a: 'x', extra: 'unused' })).toThrow(
      /token "extra" was provided but not used/,
    );
  });

  it('accepts whitespace inside the token braces', () => {
    const out = renderStub('Hi {{ name }}', { name: 'X' });
    expect(out).toBe('Hi X');
  });

  it('handles empty replacement values', () => {
    const out = renderStub('A{{x}}B', { x: '' });
    expect(out).toBe('AB');
  });
});

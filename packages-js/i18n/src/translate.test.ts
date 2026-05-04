import { describe, expect, it } from 'vitest';
import { buildTranslator } from './translate';

describe('buildTranslator', () => {
  const dict = {
    actions: { save: 'Save', cancel: 'Cancel' },
    messages: { welcome: 'Hello :name', count: 'You have :n items' },
  };
  const t = buildTranslator(dict);

  it('returns the leaf for a dotted key', () => {
    expect(t('actions.save')).toBe('Save');
  });

  it('returns the original key when missing', () => {
    expect(t('actions.unknown')).toBe('actions.unknown');
  });

  it('substitutes :placeholder with params', () => {
    expect(t('messages.welcome', { name: 'Diogo' })).toBe('Hello Diogo');
  });

  it('coerces numeric params to strings', () => {
    expect(t('messages.count', { n: 7 })).toBe('You have 7 items');
  });

  it('keeps unknown :placeholders verbatim', () => {
    expect(t('messages.welcome', { other: 'x' })).toBe('Hello :name');
  });

  it('handles nested keys deeper than two segments', () => {
    const deep = buildTranslator({ a: { b: { c: 'leaf' } } });
    expect(deep('a.b.c')).toBe('leaf');
  });
});

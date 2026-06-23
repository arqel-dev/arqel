import type { FieldSchema } from '@arqel-dev/types/fields';
import { renderHook } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { route } from '../src/utils/route.js';
import {
  buildInitialFormState,
  fieldsVisibleIn,
  indexFieldsByName,
} from '../src/utils/serializeFields.js';
import { selectPluralForm, translate, useTranslator } from '../src/utils/translate.js';

describe('route()', () => {
  afterEach(() => {
    Reflect.deleteProperty(globalThis, 'route');
  });

  it('throws a clear error when Ziggy is not loaded', () => {
    expect(() => route('arqel.resources.index')).toThrow(/Ziggy/);
  });

  it('delegates to globalThis.route when defined', () => {
    const spy = vi.fn().mockReturnValue('/admin/users');
    Object.defineProperty(globalThis, 'route', {
      value: spy,
      configurable: true,
      writable: true,
    });

    const url = route('arqel.resources.index', { resource: 'users' });

    expect(url).toBe('/admin/users');
    expect(spy).toHaveBeenCalledWith('arqel.resources.index', { resource: 'users' }, undefined);
  });
});

describe('translate()', () => {
  const dict = {
    arqel: {
      actions: { create: 'Create', edit: 'Edit' },
      hello: 'Hello :name!',
    },
  };

  it('resolves a dotted key', () => {
    expect(translate(dict, 'arqel.actions.create')).toBe('Create');
  });

  it('returns the key when the path is missing', () => {
    expect(translate(dict, 'arqel.actions.publish')).toBe('arqel.actions.publish');
  });

  it('applies replacements', () => {
    expect(translate(dict, 'arqel.hello', { replacements: { name: 'World' } })).toBe(
      'Hello World!',
    );
  });

  it('selects the singular plural form for count=1 and substitutes :count', () => {
    const pdict = {
      table: { bulk: { selected: '{one} :count selected|{other} :count selected' } },
    };
    expect(
      translate(pdict, 'table.bulk.selected', {
        count: 1,
        locale: 'en',
        replacements: { count: 1 },
      }),
    ).toBe('1 selected');
    expect(
      translate(pdict, 'table.bulk.selected', {
        count: 4,
        locale: 'en',
        replacements: { count: 4 },
      }),
    ).toBe('4 selected');
  });

  it('pluralizes pt-BR forms with distinct singular/plural nouns', () => {
    const pdict = {
      table: { bulk: { selected: '{one} :count selecionado|{other} :count selecionados' } },
    };
    expect(
      translate(pdict, 'table.bulk.selected', {
        count: 1,
        locale: 'pt-BR',
        replacements: { count: 1 },
      }),
    ).toBe('1 selecionado');
    expect(
      translate(pdict, 'table.bulk.selected', {
        count: 3,
        locale: 'pt-BR',
        replacements: { count: 3 },
      }),
    ).toBe('3 selecionados');
  });
});

describe('useTranslator()', () => {
  const dict = {
    arqel: { actions: { create: 'Create' }, hello: 'Hello :name!' },
    table: { bulk: { selected: '{one} :count item|{other} :count items' } },
  };

  it('resolves keys and applies replacements', () => {
    const { result } = renderHook(() => useTranslator(dict));
    expect(result.current('arqel.actions.create')).toBe('Create');
    expect(result.current('arqel.hello', { name: 'World' })).toBe('Hello World!');
  });

  it('selects the plural form from replacements.count instead of emitting the pipe-string', () => {
    const { result } = renderHook(() => useTranslator(dict));
    expect(result.current('table.bulk.selected', { count: 1 })).toBe('1 item');
    expect(result.current('table.bulk.selected', { count: 2 })).toBe('2 items');
  });

  it('threads the active locale into pluralization', () => {
    const ptDict = {
      table: { bulk: { selected: '{one} :count selecionado|{other} :count selecionados' } },
    };
    const { result } = renderHook(() => useTranslator(ptDict, 'pt-BR'));
    expect(result.current('table.bulk.selected', { count: 1 })).toBe('1 selecionado');
    expect(result.current('table.bulk.selected', { count: 3 })).toBe('3 selecionados');
  });
});

describe('selectPluralForm()', () => {
  it('matches CLDR categories via Intl.PluralRules', () => {
    const v = '{one} :count command|{other} :count commands';
    expect(selectPluralForm(v, 1, 'en')).toBe(':count command');
    expect(selectPluralForm(v, 0, 'en')).toBe(':count commands');
    expect(selectPluralForm(v, 7, 'en')).toBe(':count commands');
  });

  it('honors explicit exact-count and range selectors', () => {
    const v = '{0} none|{1} just one|[2,*] many';
    expect(selectPluralForm(v, 0, 'en')).toBe('none');
    expect(selectPluralForm(v, 1, 'en')).toBe('just one');
    expect(selectPluralForm(v, 9, 'en')).toBe('many');
  });

  it('falls back to positional singular|plural ordering', () => {
    const v = 'apple|apples';
    expect(selectPluralForm(v, 1, 'en')).toBe('apple');
    expect(selectPluralForm(v, 2, 'en')).toBe('apples');
  });

  it('returns the lone form unchanged when no separator is present', () => {
    expect(selectPluralForm('just text', 5, 'en')).toBe('just text');
  });
});

const baseField: FieldSchema = {
  type: 'text',
  name: 'email',
  label: 'Email',
  component: 'TextInput',
  required: true,
  readonly: false,
  disabled: false,
  placeholder: null,
  helperText: null,
  defaultValue: null,
  columnSpan: 1,
  live: false,
  liveDebounce: null,
  validation: { rules: [], messages: {}, attribute: null },
  visibility: { create: true, edit: true, detail: true, table: true, canSee: true },
  dependsOn: [],
  props: {},
};

describe('buildInitialFormState()', () => {
  it('uses the record value when present', () => {
    const state = buildInitialFormState([baseField], { email: 'a@b.com' });

    expect(state).toEqual({ email: 'a@b.com' });
  });

  it('falls back to defaultValue, then to "" for text fields', () => {
    const withDefault = { ...baseField, defaultValue: 'default@example.com' };

    expect(buildInitialFormState([withDefault])).toEqual({ email: 'default@example.com' });
    expect(buildInitialFormState([baseField])).toEqual({ email: '' });
  });

  it('uses false for boolean / [] for hasMany / null for number', () => {
    const boolean: FieldSchema = { ...baseField, name: 'active', type: 'boolean', props: {} };
    const hasMany: FieldSchema = {
      ...baseField,
      name: 'tags',
      type: 'hasMany',
      props: { relatedResource: 'X', relationship: 'tags' },
    };
    const number: FieldSchema = { ...baseField, name: 'qty', type: 'number', props: {} };

    expect(buildInitialFormState([boolean, hasMany, number])).toEqual({
      active: false,
      tags: [],
      qty: null,
    });
  });

  it('skips fields with canSee=false', () => {
    const hidden: FieldSchema = {
      ...baseField,
      name: 'secret',
      visibility: { ...baseField.visibility, canSee: false },
    };

    expect(buildInitialFormState([baseField, hidden])).toEqual({ email: '' });
  });
});

describe('field utilities', () => {
  it('indexFieldsByName produces a name→field map', () => {
    const a: FieldSchema = { ...baseField, name: 'a' };
    const b: FieldSchema = { ...baseField, name: 'b' };

    expect(indexFieldsByName([a, b])).toEqual({ a, b });
  });

  it('fieldsVisibleIn filters by per-context flags', () => {
    const visible: FieldSchema = baseField;
    const tableOnly: FieldSchema = {
      ...baseField,
      name: 'computed',
      visibility: {
        create: false,
        edit: false,
        detail: false,
        table: true,
        canSee: true,
      },
    };

    expect(fieldsVisibleIn([visible, tableOnly], 'create')).toHaveLength(1);
    expect(fieldsVisibleIn([visible, tableOnly], 'table')).toHaveLength(2);
  });
});

import type { FieldSchema } from '@arqel-dev/types/fields';
import { afterEach, describe, expect, it, vi } from 'vitest';

import { route } from '../src/utils/route.js';
import {
  buildInitialFormState,
  fieldsVisibleIn,
  indexFieldsByName,
} from '../src/utils/serializeFields.js';
import { translate } from '../src/utils/translate.js';

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

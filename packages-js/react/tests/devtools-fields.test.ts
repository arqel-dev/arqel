import { describe, expect, it } from 'vitest';

import { createDevToolsHook, extractFieldsSchema } from '../src/devtools/devtools.js';

describe('extractFieldsSchema (DEVTOOLS-005)', () => {
  it('returns [] for empty / null pageProps', () => {
    expect(extractFieldsSchema(null)).toEqual([]);
    expect(extractFieldsSchema(undefined)).toEqual([]);
    expect(extractFieldsSchema({})).toEqual([]);
    expect(extractFieldsSchema(42)).toEqual([]);
  });

  it('extracts from pageProps.fields', () => {
    const pageProps = {
      fields: [
        { name: 'email', type: 'text', label: 'Email', required: true },
        { name: 'role', type: 'select', visible: false },
      ],
    };
    const result = extractFieldsSchema(pageProps);
    expect(result).toHaveLength(2);
    expect(result[0]).toMatchObject({
      name: 'email',
      type: 'text',
      label: 'Email',
      required: true,
      visible: true,
    });
    expect(result[1]).toMatchObject({
      name: 'role',
      type: 'select',
      required: false,
      visible: false,
    });
  });

  it('falls back to pageProps.resource.fields when fields is missing', () => {
    const pageProps = {
      resource: {
        fields: [{ name: 'title', type: 'text' }],
      },
    };
    const result = extractFieldsSchema(pageProps);
    expect(result).toHaveLength(1);
    expect(result[0]?.name).toBe('title');
  });

  it('falls back to pageProps.form.fields when fields and resource.fields are missing', () => {
    const pageProps = {
      form: {
        fields: [{ name: 'amount', type: 'number' }],
      },
    };
    const result = extractFieldsSchema(pageProps);
    expect(result).toHaveLength(1);
    expect(result[0]?.name).toBe('amount');
    expect(result[0]?.type).toBe('number');
  });

  it('normalizes fields with missing keys to defaults (visible=true, required=false)', () => {
    const pageProps = { fields: [{ name: 'x', type: 'text' }] };
    const result = extractFieldsSchema(pageProps);
    expect(result[0]).toMatchObject({
      name: 'x',
      type: 'text',
      required: false,
      visible: true,
    });
    expect(result[0]?.label).toBeUndefined();
    expect(result[0]?.rules).toBeUndefined();
  });

  it('preserves rules array and meta object', () => {
    const pageProps = {
      fields: [
        {
          name: 'email',
          type: 'text',
          rules: ['required', 'email'],
          meta: { dependsOn: 'role' },
        },
      ],
    };
    const result = extractFieldsSchema(pageProps);
    expect(result[0]?.rules).toEqual(['required', 'email']);
    expect(result[0]?.meta).toEqual({ dependsOn: 'role' });
  });
});

describe('hook.getFieldsSchema() integration', () => {
  it('returns [] before any pageProps update', () => {
    const hook = createDevToolsHook('test');
    expect(hook.getFieldsSchema()).toEqual([]);
  });

  it('updates fieldsSchema when setPageProps is called', () => {
    const hook = createDevToolsHook('test');
    hook.setPageProps({ fields: [{ name: 'email', type: 'text' }] }, {}, '/admin/users/create');
    const schema = hook.getFieldsSchema();
    expect(schema).toHaveLength(1);
    expect(schema[0]?.name).toBe('email');
    expect(hook.getState().fieldsSchema).toBe(schema);
  });
});

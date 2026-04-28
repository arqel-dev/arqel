import { expectTypeOf } from 'expect-type';
import { describe, expect, it } from 'vitest';
import {
  type BelongsToFieldSchema,
  type FieldSchema,
  isFieldType,
  type SelectFieldSchema,
  type TextFieldSchema,
} from '../src/fields.js';

describe('FieldSchema discriminated union', () => {
  it('narrows on `type`', () => {
    const field: FieldSchema = {
      type: 'text',
      name: 'first_name',
      label: 'First name',
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
      validation: { rules: ['required'], messages: {}, attribute: null },
      visibility: { create: true, edit: true, detail: true, table: true, canSee: true },
      dependsOn: [],
      props: { maxLength: 255 },
    };

    if (isFieldType(field, 'text')) {
      expectTypeOf(field).toEqualTypeOf<TextFieldSchema>();
      expectTypeOf(field.props.maxLength).toEqualTypeOf<number | undefined>();
    }

    expect(field.type).toBe('text');
  });

  it('discriminates select fields', () => {
    const field: FieldSchema = {
      type: 'select',
      name: 'role',
      label: 'Role',
      component: 'SelectInput',
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
      props: {
        options: [{ value: 'admin', label: 'Admin' }],
      },
    };

    if (isFieldType(field, 'select')) {
      expectTypeOf(field).toEqualTypeOf<SelectFieldSchema>();
      expectTypeOf(field.props.options).toMatchTypeOf<unknown>();
    }

    expect(field.type).toBe('select');
  });

  it('discriminates belongsTo fields with relatedResource', () => {
    const field: FieldSchema = {
      type: 'belongsTo',
      name: 'owner_id',
      label: 'Owner',
      component: 'BelongsToInput',
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
      props: {
        relatedResource: 'App\\Arqel\\Resources\\UserResource',
        relationship: 'owner',
        searchable: true,
        searchColumns: ['name'],
        preload: false,
      },
    };

    if (isFieldType(field, 'belongsTo')) {
      expectTypeOf(field).toEqualTypeOf<BelongsToFieldSchema>();
      expectTypeOf(field.props.relatedResource).toEqualTypeOf<string>();
    }

    expect(field.props).toMatchObject({ relatedResource: expect.any(String) });
  });

  it('isFieldType returns false for non-matching types', () => {
    const field: FieldSchema = {
      type: 'text',
      name: 'x',
      label: null,
      component: null,
      required: false,
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

    expect(isFieldType(field, 'select')).toBe(false);
    expect(isFieldType(field, 'text')).toBe(true);
  });
});

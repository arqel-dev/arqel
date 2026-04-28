import { expectTypeOf } from 'expect-type';
import { describe, expect, it } from 'vitest';
import type { FieldSchema } from '../src/fields.js';
import {
  type FieldEntry,
  isFieldEntry,
  isLayoutEntry,
  type LayoutEntry,
  resolveFieldEntry,
  type SchemaEntry,
  type SectionEntry,
} from '../src/forms.js';

describe('SchemaEntry discriminated union', () => {
  it('isFieldEntry narrows to FieldEntry', () => {
    const entry: SchemaEntry = { kind: 'field', name: 'email', type: 'text' };

    if (isFieldEntry(entry)) {
      expectTypeOf(entry).toEqualTypeOf<FieldEntry>();
    }

    expect(isFieldEntry(entry)).toBe(true);
  });

  it('isLayoutEntry narrows to LayoutEntry', () => {
    const entry: SchemaEntry = {
      kind: 'layout',
      type: 'section',
      component: 'FormSection',
      columnSpan: 'full',
      props: { heading: 'Profile', columns: 2 },
    };

    if (isLayoutEntry(entry)) {
      expectTypeOf(entry).toMatchTypeOf<LayoutEntry>();
    }

    expect(isLayoutEntry(entry)).toBe(true);
  });

  it('SectionEntry props carry heading + columns', () => {
    const section: SectionEntry = {
      kind: 'layout',
      type: 'section',
      component: 'FormSection',
      columnSpan: 1,
      props: { heading: 'Profile', columns: 2, compact: true },
    };

    expectTypeOf(section.props.heading).toEqualTypeOf<string>();
    expect(section.props.heading).toBe('Profile');
  });
});

describe('resolveFieldEntry', () => {
  const fields: FieldSchema[] = [
    {
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
    },
  ];

  it('returns the matching field by name', () => {
    const entry: FieldEntry = { kind: 'field', name: 'email', type: 'text' };

    expect(resolveFieldEntry(entry, fields)?.name).toBe('email');
  });

  it('returns null when missing', () => {
    const entry: FieldEntry = { kind: 'field', name: 'nope', type: 'text' };

    expect(resolveFieldEntry(entry, fields)).toBeNull();
  });
});

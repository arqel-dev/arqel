import type { BelongsToFieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { BelongsToInput } from '../src/relationship/index.js';

const baseField = {
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
};

function makeBelongsTo(props: Partial<BelongsToFieldSchema['props']> = {}): BelongsToFieldSchema {
  return {
    ...baseField,
    type: 'belongsTo',
    name: 'author',
    label: 'Author',
    component: 'BelongsToInput',
    props: {
      relatedResource: 'users',
      relationship: 'author',
      searchable: true,
      searchColumns: ['name'],
      preload: false,
      // Intentionally no searchRoute: the async search never fires, so
      // `results` stays empty — this is exactly the "just mounted an
      // edit form" state where the bug showed the raw FK id.
      ...props,
    },
  };
}

describe('BelongsToInput selectedLabel fallback', () => {
  it('shows the server-resolved selectedLabel when search results are empty', () => {
    const field = makeBelongsTo({ selectedLabel: 'Ada Lovelace' });
    render(<BelongsToInput field={field} value={42} onChange={vi.fn()} />);

    const input = screen.getByRole('combobox') as HTMLInputElement;
    expect(input.value).toBe('Ada Lovelace');
    expect(input.value).not.toBe('42');
  });

  it('falls back to the raw value when no selectedLabel is provided (retrocompat)', () => {
    const field = makeBelongsTo();
    render(<BelongsToInput field={field} value={42} onChange={vi.fn()} />);

    const input = screen.getByRole('combobox') as HTMLInputElement;
    expect(input.value).toBe('42');
  });
});

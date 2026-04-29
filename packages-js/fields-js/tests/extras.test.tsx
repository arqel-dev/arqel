import type { FieldSchema } from '@arqel/types/fields';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { ColorInput } from '../src/color/index.js';
import { HiddenInput } from '../src/hidden/index.js';
import { SlugInput, slugify } from '../src/slug/index.js';

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

const slug: FieldSchema = {
  ...baseField,
  type: 'slug',
  name: 'slug',
  label: 'Slug',
  component: 'SlugInput',
  props: {},
};

const color: FieldSchema = {
  ...baseField,
  type: 'color',
  name: 'theme',
  label: 'Theme',
  component: 'ColorInput',
  props: { presets: ['#ff0000', '#00ff00'] },
};

const hidden: FieldSchema = {
  ...baseField,
  type: 'hidden',
  name: 'token',
  label: '',
  component: 'HiddenInput',
  props: {},
};

describe('slugify', () => {
  it('lowercases and replaces non-alphanumerics', () => {
    expect(slugify('Hello World!')).toBe('hello-world');
  });

  it('strips leading and trailing dashes', () => {
    expect(slugify(' --foo--bar-- ')).toBe('foo-bar');
  });

  it('handles diacritics', () => {
    expect(slugify('São Paulo')).toBe('sao-paulo');
  });
});

describe('SlugInput', () => {
  it('emits slugified text', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<SlugInput field={slug} value="" onChange={onChange} />);
    await user.type(screen.getByRole('textbox'), 'A');
    expect(onChange).toHaveBeenCalledWith('a');
  });
});

describe('ColorInput', () => {
  it('emits chosen preset color', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();
    render(<ColorInput field={color} value="#000000" onChange={onChange} />);
    await user.click(screen.getByRole('button', { name: 'Preset #ff0000' }));
    expect(onChange).toHaveBeenCalledWith('#ff0000');
  });
});

describe('HiddenInput', () => {
  it('renders an input with type=hidden', () => {
    const { container } = render(<HiddenInput field={hidden} value="abc" onChange={() => {}} />);
    const input = container.querySelector('input[type="hidden"]') as HTMLInputElement;
    expect(input).toBeInTheDocument();
    expect(input.value).toBe('abc');
  });
});

import type { FieldSchema } from '@arqel-dev/types/fields';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { ColorInput } from '../src/color/index.js';
import { SlugInput } from '../src/slug/index.js';
import { setMockTranslations } from './setup.js';

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
  name: 'tint',
  label: 'Tint',
  component: 'ColorInput',
  props: { presets: ['#ff0000'] },
};

describe('SlugInput placeholder (i18n)', () => {
  it('falls back to the English example slug with no dictionary', () => {
    const { container } = render(<SlugInput field={slug} value="" onChange={vi.fn()} />);
    const input = container.querySelector('input[type="text"]') as HTMLInputElement;
    expect(input.placeholder).toBe('my-resource-slug');
  });

  it('localizes the default placeholder', () => {
    setMockTranslations({
      arqel: { fields: { slug: { placeholder: 'meu-slug-de-recurso' } } },
    });
    const { container } = render(<SlugInput field={slug} value="" onChange={vi.fn()} />);
    const input = container.querySelector('input[type="text"]') as HTMLInputElement;
    expect(input.placeholder).toBe('meu-slug-de-recurso');
  });

  it('still prefers an explicit field placeholder over the template', () => {
    const explicit: FieldSchema = { ...slug, placeholder: 'custom-slug' };
    const { container } = render(<SlugInput field={explicit} value="" onChange={vi.fn()} />);
    const input = container.querySelector('input[type="text"]') as HTMLInputElement;
    expect(input.placeholder).toBe('custom-slug');
  });
});

describe('ColorInput preset swatch a11y (i18n)', () => {
  it('falls back to "Preset :color" in English', () => {
    render(<ColorInput field={color} value="#000000" onChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Preset #ff0000' })).toBeInTheDocument();
  });

  it('localizes the preset aria-label with the interpolated color', () => {
    setMockTranslations({
      arqel: { fields: { color: { preset_aria: 'Predefinição :color' } } },
    });
    render(<ColorInput field={color} value="#000000" onChange={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Predefinição #ff0000' })).toBeInTheDocument();
  });
});

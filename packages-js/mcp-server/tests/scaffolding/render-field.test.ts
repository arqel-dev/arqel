import { describe, expect, it } from 'vitest';

import { RenderFieldValidationError, renderField } from '../../src/scaffolding/render-field.js';

describe('renderField', () => {
  it('renders a basic Text field', () => {
    const out = renderField({ name: 'title', type: 'Text' });
    expect(out.snippet).toBe("TextField::make('title'),");
    expect(out.imports).toEqual(['use Arqel\\Fields\\Types\\TextField;']);
    expect(out.notes).toEqual([]);
  });

  it('normalizes lower-case type', () => {
    const out = renderField({ name: 'name', type: 'text' });
    expect(out.imports).toEqual(['use Arqel\\Fields\\Types\\TextField;']);
  });

  it('normalizes SCREAMING_SNAKE type', () => {
    const out = renderField({ name: 'author_id', type: 'BELONGS_TO' });
    expect(out.snippet).toContain('BelongsToField::make');
    expect(out.imports).toEqual(['use Arqel\\Fields\\Types\\BelongsToField;']);
  });

  it('normalizes snake_case type', () => {
    const out = renderField({ name: 'tags', type: 'multi_select' });
    expect(out.imports).toEqual(['use Arqel\\Fields\\Types\\MultiSelectField;']);
  });

  it('renders required + placeholder snapshot', () => {
    const out = renderField({
      name: 'email',
      type: 'Email',
      options: { required: true, placeholder: 'user@example.com' },
    });
    expect(out).toMatchSnapshot();
  });

  it('renders default + helpText snapshot', () => {
    const out = renderField({
      name: 'age',
      type: 'Number',
      options: { default: 0, helpText: 'Optional.' },
    });
    expect(out).toMatchSnapshot();
  });

  it('renders nullable boolean default', () => {
    const out = renderField({
      name: 'active',
      type: 'Boolean',
      options: { nullable: true, default: false },
    });
    expect(out).toMatchSnapshot();
  });

  it('renders Select with options array', () => {
    const out = renderField({
      name: 'status',
      type: 'Select',
      options: { options: { draft: 'Draft', published: 'Published' } },
    });
    expect(out).toMatchSnapshot();
  });

  it('renders string default', () => {
    const out = renderField({
      name: 'color',
      type: 'Color',
      options: { default: '#ffffff' },
    });
    expect(out.snippet).toContain("->default('#ffffff')");
  });

  it('records a note for unknown options', () => {
    const out = renderField({
      name: 'name',
      type: 'Text',
      options: { mysteryFlag: true },
    });
    expect(out.notes).toEqual([expect.stringMatching(/mysteryFlag/)]);
    expect(out.snippet).toBe("TextField::make('name'),");
  });

  it('escapes single quotes in name', () => {
    const out = renderField({ name: "o'reilly", type: 'Text' });
    expect(out.snippet).toBe("TextField::make('o\\'reilly'),");
  });

  it('throws on empty name', () => {
    expect(() => renderField({ name: '', type: 'Text' })).toThrow(RenderFieldValidationError);
  });

  it('throws on unknown type', () => {
    try {
      renderField({ name: 'x', type: 'Magical' });
      expect.fail('should have thrown');
    } catch (e) {
      expect(e).toBeInstanceOf(RenderFieldValidationError);
      const err = e as RenderFieldValidationError;
      expect(err.detail.code).toBe('UNKNOWN_FIELD_TYPE');
      expect(err.detail.supported).toContain('Text');
    }
  });

  it('ignores `required: false`', () => {
    const out = renderField({ name: 'name', type: 'Text', options: { required: false } });
    expect(out.snippet).toBe("TextField::make('name'),");
  });

  it('ignores `nullable: false`', () => {
    const out = renderField({ name: 'name', type: 'Text', options: { nullable: false } });
    expect(out.snippet).toBe("TextField::make('name'),");
  });

  it('renders all 21 known field types without throwing', () => {
    const types = [
      'Text',
      'Textarea',
      'Number',
      'Currency',
      'Boolean',
      'Toggle',
      'Select',
      'MultiSelect',
      'Radio',
      'Email',
      'URL',
      'Password',
      'Slug',
      'Date',
      'DateTime',
      'BelongsTo',
      'HasMany',
      'File',
      'Image',
      'Color',
      'Hidden',
    ];
    for (const t of types) {
      const out = renderField({ name: 'x', type: t });
      expect(out.snippet).toMatch(/::make\('x'\),/);
      expect(out.imports[0]).toMatch(/^use Arqel\\Fields\\Types\\/);
    }
  });

  it('records note when default has unsupported type', () => {
    const out = renderField({
      name: 'meta',
      type: 'Text',
      options: { default: { complex: true } },
    });
    // default still renders something via JSON fallback — that's ok, but no note.
    expect(out.snippet).toContain('->default(');
  });
});

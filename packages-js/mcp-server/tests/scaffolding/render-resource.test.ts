import { describe, expect, it } from 'vitest';

import {
  RenderResourceValidationError,
  renderResource,
} from '../../src/scaffolding/render-resource.js';

describe('renderResource', () => {
  it('renders a 3-field Post resource snapshot', () => {
    const out = renderResource({
      model: 'Post',
      fields: [
        { name: 'title', type: 'Text', options: { required: true } },
        { name: 'body', type: 'Textarea' },
        { name: 'published', type: 'Boolean', options: { default: false } },
      ],
    });
    expect(out).toMatchSnapshot();
  });

  it('defaults namespace to App\\Arqel\\Resources', () => {
    const out = renderResource({ model: 'Post', fields: [] });
    expect(out.files[0]?.path).toBe('app/Arqel/Resources/PostResource.php');
    expect(out.files[0]?.content).toContain('namespace App\\Arqel\\Resources;');
  });

  it('normalizes basename model to App\\Models\\<Name>', () => {
    const out = renderResource({ model: 'Post', fields: [] });
    expect(out.files[0]?.content).toContain('use App\\Models\\Post;');
  });

  it('preserves explicit FQCN model', () => {
    const out = renderResource({ model: 'Domain\\Blog\\Post', fields: [] });
    expect(out.files[0]?.content).toContain('use Domain\\Blog\\Post;');
  });

  it('strips a leading backslash on FQCN', () => {
    const out = renderResource({ model: '\\App\\Models\\Post', fields: [] });
    expect(out.files[0]?.content).toContain('use App\\Models\\Post;');
  });

  it('rejects duplicate field names', () => {
    expect(() =>
      renderResource({
        model: 'Post',
        fields: [
          { name: 'name', type: 'Text' },
          { name: 'name', type: 'Email' },
        ],
      }),
    ).toThrow(RenderResourceValidationError);
  });

  it('rejects empty model', () => {
    expect(() => renderResource({ model: '   ', fields: [] })).toThrow(
      RenderResourceValidationError,
    );
  });

  it('rejects invalid model identifier', () => {
    expect(() => renderResource({ model: '123Invalid', fields: [] })).toThrow(
      RenderResourceValidationError,
    );
  });

  it('rejects invalid namespace', () => {
    expect(() =>
      renderResource({ model: 'Post', fields: [], namespace: '1Bad\\Namespace' }),
    ).toThrow(RenderResourceValidationError);
  });

  it('rejects invalid resource name', () => {
    expect(() => renderResource({ model: 'Post', fields: [], resourceName: 'has space' })).toThrow(
      RenderResourceValidationError,
    );
  });

  it('uses custom namespace and resource name', () => {
    const out = renderResource({
      model: 'Post',
      fields: [],
      namespace: 'App\\Admin\\Resources',
      resourceName: 'BlogPostResource',
    });
    expect(out.files[0]?.path).toBe('app/Admin/Resources/BlogPostResource.php');
    expect(out.files[0]?.content).toContain('namespace App\\Admin\\Resources;');
    expect(out.files[0]?.content).toContain('final class BlogPostResource extends Resource');
  });

  it('injects field imports sorted alphabetically', () => {
    const out = renderResource({
      model: 'Post',
      fields: [
        { name: 'title', type: 'Text' },
        { name: 'author_id', type: 'BelongsTo' },
      ],
    });
    const content = out.files[0]!.content;
    const belongsToIdx = content.indexOf('BelongsToField');
    const textIdx = content.indexOf('TextField;');
    expect(belongsToIdx).toBeGreaterThan(-1);
    expect(textIdx).toBeGreaterThan(-1);
    expect(belongsToIdx).toBeLessThan(textIdx);
  });

  it('includes default install/discovery notes', () => {
    const out = renderResource({ model: 'Post', fields: [] });
    expect(out.notes.some((n) => n.includes('arqel:install'))).toBe(true);
    expect(out.notes.some((n) => n.includes('service provider discovery'))).toBe(true);
  });

  it('produces deterministic output across calls', () => {
    const a = renderResource({ model: 'Post', fields: [{ name: 'name', type: 'Text' }] });
    const b = renderResource({ model: 'Post', fields: [{ name: 'name', type: 'Text' }] });
    expect(a).toEqual(b);
  });

  it('propagates UNKNOWN_FIELD_TYPE through resource validation', () => {
    try {
      renderResource({ model: 'Post', fields: [{ name: 'x', type: 'Magic' }] });
      expect.fail('should have thrown');
    } catch (e) {
      const err = e as RenderResourceValidationError;
      expect(err.detail.code).toBe('UNKNOWN_FIELD_TYPE');
    }
  });
});

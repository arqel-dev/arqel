import { describe, expect, it } from 'vitest';

import { generateResource, generateResourceTool } from '../../src/tools/generate-resource.js';

describe('generate_resource tool', () => {
  it('happy path: produces a single PHP file', () => {
    const result = generateResource({
      model: 'Post',
      fields: [{ name: 'title', type: 'Text' }],
    });
    expect(result).toHaveProperty('files');
    if ('files' in result) {
      expect(result.files).toHaveLength(1);
      expect(result.files[0]?.path).toBe('app/Arqel/Resources/PostResource.php');
      expect(result.files[0]?.language).toBe('php');
    }
  });

  it('error envelope: duplicate field name', () => {
    const result = generateResource({
      model: 'Post',
      fields: [
        { name: 'a', type: 'Text' },
        { name: 'a', type: 'Email' },
      ],
    });
    expect(result).toHaveProperty('error');
    if ('error' in result) {
      expect(result.error.code).toBe('DUPLICATE_FIELD_NAME');
    }
  });

  it('error envelope: unknown field type', () => {
    const result = generateResource({
      model: 'Post',
      fields: [{ name: 'x', type: 'Quantum' }],
    });
    expect(result).toHaveProperty('error');
    if ('error' in result) {
      expect(result.error.code).toBe('UNKNOWN_FIELD_TYPE');
    }
  });

  it('handler: INVALID_INPUT when model is missing', async () => {
    const out = await generateResourceTool.handle({});
    expect(out.isError).toBe(true);
    const body = JSON.parse(out.content[0]!.text);
    expect(body.error.code).toBe('INVALID_INPUT');
  });

  it('handler: serializes happy path', async () => {
    const out = await generateResourceTool.handle({
      model: 'Post',
      fields: [{ name: 'title', type: 'Text' }],
    });
    expect(out.isError).toBeUndefined();
    const body = JSON.parse(out.content[0]!.text);
    expect(body.files[0].content).toContain("TextField::make('title')");
  });

  it('handler: defaults fields to empty array', async () => {
    const out = await generateResourceTool.handle({ model: 'Post' });
    expect(out.isError).toBeUndefined();
  });

  it('definition: name and required', () => {
    expect(generateResourceTool.definition.name).toBe('generate_resource');
    expect(generateResourceTool.definition.inputSchema.required).toEqual(['model']);
  });

  it('error envelope: invalid namespace', () => {
    const result = generateResource({
      model: 'Post',
      fields: [],
      namespace: '1Bad',
    });
    expect(result).toHaveProperty('error');
    if ('error' in result) {
      expect(result.error.code).toBe('INVALID_NAMESPACE');
    }
  });
});

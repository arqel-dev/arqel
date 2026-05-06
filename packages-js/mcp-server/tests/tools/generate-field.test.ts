import { describe, expect, it } from 'vitest';

import { generateField, generateFieldTool } from '../../src/tools/generate-field.js';

describe('generate_field tool', () => {
  it('happy path: returns snippet + imports', () => {
    const result = generateField({ name: 'email', type: 'Email' });
    expect(result).toEqual({
      snippet: "EmailField::make('email'),",
      imports: ['use Arqel\\Fields\\Types\\EmailField;'],
      notes: [],
    });
  });

  it('error envelope on unknown type', () => {
    const result = generateField({ name: 'x', type: 'Quantum' });
    expect(result).toHaveProperty('error');
    if ('error' in result) {
      expect(result.error.code).toBe('UNKNOWN_FIELD_TYPE');
      expect(result.error.supported).toContain('Text');
    }
  });

  it('handler: returns INVALID_INPUT for missing name', async () => {
    const out = await generateFieldTool.handle({ type: 'Text' });
    expect(out.isError).toBe(true);
    const body = JSON.parse(out.content[0]!.text);
    expect(body.error.code).toBe('INVALID_INPUT');
  });

  it('handler: serializes a happy path response', async () => {
    const out = await generateFieldTool.handle({ name: 'name', type: 'Text' });
    expect(out.isError).toBeUndefined();
    const body = JSON.parse(out.content[0]!.text);
    expect(body.snippet).toBe("TextField::make('name'),");
  });

  it('handler: error envelope serialised with isError=true', async () => {
    const out = await generateFieldTool.handle({ name: 'x', type: 'Nope' });
    expect(out.isError).toBe(true);
    const body = JSON.parse(out.content[0]!.text);
    expect(body.error.code).toBe('UNKNOWN_FIELD_TYPE');
  });

  it('definition: name and required fields', () => {
    expect(generateFieldTool.definition.name).toBe('generate_field');
    expect(generateFieldTool.definition.inputSchema.required).toEqual(['name', 'type']);
  });
});

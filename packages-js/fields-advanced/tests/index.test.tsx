import { clearFieldRegistry, getFieldComponent, getRegisteredFields } from '@arqel/ui/form';
import { describe, expect, it } from 'vitest';

const ALL_ADVANCED_FIELDS = [
  'RichTextInput',
  'MarkdownInput',
  'CodeInput',
  'RepeaterInput',
  'BuilderInput',
  'KeyValueInput',
  'TagsInput',
  'WizardInput',
];

describe('@arqel/fields-advanced — register side-effect', () => {
  it('registers all 8 advanced field component slots without throwing', async () => {
    clearFieldRegistry();
    await expect(import('../src/register.js')).resolves.toBeDefined();

    for (const name of ALL_ADVANCED_FIELDS) {
      expect(getFieldComponent(name)).toBeDefined();
    }
    expect(getRegisteredFields()).toEqual([...ALL_ADVANCED_FIELDS].sort());
  });
});

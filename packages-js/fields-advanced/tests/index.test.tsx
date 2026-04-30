import { clearFieldRegistry, getFieldComponent, getRegisteredFields } from '@arqel/ui/form';
import { describe, expect, it, vi } from 'vitest';

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

const LAZY_MODULE_PATHS: Record<string, () => Promise<unknown>> = {
  RichTextInput: () => import('../src/rich-text/RichTextInput.js'),
  MarkdownInput: () => import('../src/markdown/MarkdownInput.js'),
  CodeInput: () => import('../src/code/CodeInput.js'),
  RepeaterInput: () => import('../src/repeater/RepeaterInput.js'),
  BuilderInput: () => import('../src/builder/BuilderInput.js'),
  KeyValueInput: () => import('../src/key-value/KeyValueInput.js'),
  TagsInput: () => import('../src/tags/TagsInput.js'),
  WizardInput: () => import('../src/wizard/WizardInput.js'),
};

describe('@arqel/fields-advanced — register side-effect', () => {
  it('registers all 8 advanced field component slots without throwing', async () => {
    clearFieldRegistry();
    await expect(import('../src/register.js')).resolves.toBeDefined();

    for (const name of ALL_ADVANCED_FIELDS) {
      expect(getFieldComponent(name)).toBeDefined();
    }
    expect(getRegisteredFields()).toEqual([...ALL_ADVANCED_FIELDS].sort());
  });

  it('resolves a non-null component for every registered field name', async () => {
    vi.resetModules();
    const formModule: typeof import('@arqel/ui/form') = await import('@arqel/ui/form');
    formModule.clearFieldRegistry();
    await import('../src/register.js');

    for (const name of ALL_ADVANCED_FIELDS) {
      const component = formModule.getFieldComponent(name);
      expect(component, `expected ${name} to be registered`).toBeDefined();
      expect(component).not.toBeNull();
    }
  });

  it('dynamically imports every underlying lazy module without throwing', async () => {
    for (const name of ALL_ADVANCED_FIELDS) {
      const loader = LAZY_MODULE_PATHS[name];
      if (!loader) {
        throw new Error(`missing lazy loader entry for ${name}`);
      }
      try {
        const mod = (await loader()) as Record<string, unknown>;
        expect(mod[name], `module for ${name} did not export ${name}`).toBeDefined();
      } catch (error) {
        throw new Error(
          `dynamic import of ${name} threw: ${error instanceof Error ? error.message : String(error)}`,
        );
      }
    }
  });
});

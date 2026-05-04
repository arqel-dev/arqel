import { clearFieldRegistry, getFieldComponent, getRegisteredFields } from '@arqel-dev/ui/form';
import { describe, expect, it } from 'vitest';

const ALL_FIELDS = [
  'TextInput',
  'TextareaInput',
  'EmailInput',
  'UrlInput',
  'PasswordInput',
  'NumberInput',
  'CurrencyInput',
  'Checkbox',
  'Toggle',
  'SelectInput',
  'MultiSelectInput',
  'RadioGroup',
  'BelongsToInput',
  'HasManyReadonly',
  'DateInput',
  'DateTimeInput',
  'FileInput',
  'ImageInput',
  'SlugInput',
  'ColorInput',
  'HiddenInput',
];

describe('register side-effect', () => {
  it('registers all 21 built-in components and exposes them via getRegisteredFields', async () => {
    clearFieldRegistry();
    await import('../src/register.js');
    for (const name of ALL_FIELDS) {
      expect(getFieldComponent(name)).toBeDefined();
    }
    expect(getRegisteredFields()).toEqual([...ALL_FIELDS].sort());
  });
});

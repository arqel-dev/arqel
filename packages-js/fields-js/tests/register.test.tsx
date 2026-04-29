import { clearFieldRegistry, getFieldComponent } from '@arqel/ui/form';
import { afterEach, describe, expect, it } from 'vitest';

describe('register side-effect', () => {
  afterEach(() => clearFieldRegistry());

  it('registers all 9 built-in components', async () => {
    await import('../src/register.js');
    for (const name of [
      'TextInput',
      'TextareaInput',
      'EmailInput',
      'UrlInput',
      'PasswordInput',
      'NumberInput',
      'CurrencyInput',
      'Checkbox',
      'Toggle',
    ]) {
      expect(getFieldComponent(name)).toBeDefined();
    }
  });
});

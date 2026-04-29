/**
 * Side-effect import that registers every built-in field component
 * into `@arqel/ui`'s FieldRegistry.
 *
 *   import '@arqel/fields/register';
 *
 * The component name passed to `registerField()` matches the value
 * emitted by `Field::component()` server-side (e.g. `'TextInput'`).
 * Apps can re-register their own components after this import to
 * override defaults.
 */

import { registerField } from '@arqel/ui/form';
import { Checkbox, Toggle } from './boolean/index.js';
import { CurrencyInput, NumberInput } from './number/index.js';
import { EmailInput, PasswordInput, TextareaInput, TextInput, UrlInput } from './text/index.js';

registerField('TextInput', TextInput);
registerField('TextareaInput', TextareaInput);
registerField('EmailInput', EmailInput);
registerField('UrlInput', UrlInput);
registerField('PasswordInput', PasswordInput);
registerField('NumberInput', NumberInput);
registerField('CurrencyInput', CurrencyInput);
registerField('Checkbox', Checkbox);
registerField('Toggle', Toggle);

/**
 * Side-effect import that registers every built-in field component
 * into `@arqel-dev/ui`'s FieldRegistry.
 *
 *   import '@arqel-dev/fields/register';
 *
 * The component name passed to `registerField()` matches the value
 * emitted by `Field::component()` server-side (e.g. `'TextInput'`).
 * Apps can re-register their own components after this import to
 * override defaults.
 */

import { registerField } from '@arqel-dev/ui/form';
import { Checkbox, Toggle } from './boolean/index.js';
import { ColorInput } from './color/index.js';
import { DateInput, DateTimeInput } from './date/index.js';
import { FileInput, ImageInput } from './file/index.js';
import { HiddenInput } from './hidden/index.js';
import { CurrencyInput, NumberInput } from './number/index.js';
import { BelongsToInput, HasManyReadonly } from './relationship/index.js';
import { MultiSelectInput, RadioGroup, SelectInput } from './select/index.js';
import { SlugInput } from './slug/index.js';
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
registerField('SelectInput', SelectInput);
registerField('MultiSelectInput', MultiSelectInput);
registerField('RadioGroup', RadioGroup);
registerField('BelongsToInput', BelongsToInput);
registerField('HasManyReadonly', HasManyReadonly);
registerField('DateInput', DateInput);
registerField('DateTimeInput', DateTimeInput);
registerField('FileInput', FileInput);
registerField('ImageInput', ImageInput);
registerField('SlugInput', SlugInput);
registerField('ColorInput', ColorInput);
registerField('HiddenInput', HiddenInput);

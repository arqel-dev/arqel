/**
 * @arqel-dev/fields — rich React inputs for the Arqel admin panel.
 *
 * Each component is also exported individually so apps can register
 * subsets manually. The recommended path is the side-effect import:
 *
 *   import '@arqel-dev/fields/register';
 */

export { Checkbox, Toggle } from './boolean/index.js';
export { ColorInput } from './color/index.js';
export { DateInput, DateTimeInput } from './date/index.js';
export { FileInput, ImageInput } from './file/index.js';
export { HiddenInput } from './hidden/index.js';
export { CurrencyInput, NumberInput } from './number/index.js';
export { BelongsToInput, HasManyReadonly } from './relationship/index.js';
export { MultiSelectInput, RadioGroup, SelectInput } from './select/index.js';
export { SlugInput, slugify } from './slug/index.js';
export {
  EmailInput,
  PasswordInput,
  TextareaInput,
  TextInput,
  UrlInput,
} from './text/index.js';

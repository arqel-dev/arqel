/**
 * @arqel/fields — rich React inputs for the Arqel admin panel.
 *
 * Each component is also exported individually so apps can register
 * subsets manually. The recommended path is the side-effect import:
 *
 *   import '@arqel/fields/register';
 */

export { Checkbox, Toggle } from './boolean/index.js';
export { CurrencyInput, NumberInput } from './number/index.js';
export {
  EmailInput,
  PasswordInput,
  TextareaInput,
  TextInput,
  UrlInput,
} from './text/index.js';

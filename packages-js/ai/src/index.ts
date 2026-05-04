/**
 * `@arqel-dev/ai` — React surface for the Arqel AI PHP package.
 *
 * Exports the `<AiTextInput>` apresentational component plus its type
 * contracts. Apps wiring fields through `@arqel-dev/ui`'s `FieldRegistry`
 * should also import `@arqel-dev/ai/register` once at boot to install the
 * lazy entry under the component name `AiTextInput`.
 */

export {
  AiExtractInput,
  type AiExtractInputFieldProps,
  type AiExtractInputProps,
  type AiExtractValue,
} from './AiExtractInput.js';
export {
  AiImageInput,
  type AiImageInputFieldProps,
  type AiImageInputProps,
} from './AiImageInput.js';
export {
  AiSelectInput,
  type AiSelectInputFieldProps,
  type AiSelectInputProps,
} from './AiSelectInput.js';
export {
  AiTextInput,
  type AiTextInputFieldProps,
  type AiTextInputProps,
  default,
} from './AiTextInput.js';
export {
  AiTranslateInput,
  type AiTranslateInputFieldProps,
  type AiTranslateInputProps,
  type AiTranslateValue,
} from './AiTranslateInput.js';

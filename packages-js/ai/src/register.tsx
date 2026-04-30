/**
 * Side-effect import that registers `<AiTextInput>` into `@arqel/ui`'s
 * FieldRegistry, lazily.
 *
 *   import '@arqel/ai/register';
 *
 * The component name (`AiTextInput`) matches the value emitted by
 * `Arqel\Ai\Fields\AiTextField::component()` server-side. Apps may
 * re-register their own component under the same key to override the
 * default.
 *
 * `<AiTextInput>` takes a domain-specific prop shape; the registry
 * expects a generic `{ field, value }` adapter, so we wrap here.
 */

import type { FieldSchema } from '@arqel/types/fields';
import { registerField } from '@arqel/ui/form';
import { type ComponentType, lazy, type ReactElement } from 'react';
import type { AiTextInputFieldProps, AiTextInputProps } from './AiTextInput.js';

interface RegistryFieldProps {
  field: FieldSchema;
  value: unknown;
  onChange?: (value: string) => void;
  resource?: string;
  formData?: Record<string, unknown>;
  csrfToken?: string;
}

function adaptToAiTextInput(
  Component: ComponentType<AiTextInputProps>,
): ComponentType<RegistryFieldProps> {
  return function AiTextInputAdapter(registryProps: RegistryFieldProps): ReactElement {
    const { field, value, onChange, resource, formData, csrfToken } = registryProps;
    const fieldProps = (field as unknown as { props?: AiTextInputFieldProps }).props;
    const stringValue = typeof value === 'string' ? value : '';
    return (
      <Component
        name={field.name}
        value={stringValue}
        props={fieldProps}
        {...(onChange !== undefined ? { onChange } : {})}
        {...(resource !== undefined ? { resource } : {})}
        field={field.name}
        {...(formData !== undefined ? { formData } : {})}
        {...(csrfToken !== undefined ? { csrfToken } : {})}
      />
    );
  };
}

const LazyAiTextInput = lazy(async () => {
  const mod = await import('./AiTextInput.js');
  return { default: adaptToAiTextInput(mod.AiTextInput) };
});

registerField('AiTextInput', LazyAiTextInput as unknown as Parameters<typeof registerField>[1]);

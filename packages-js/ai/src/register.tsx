/**
 * Side-effect import que registra os componentes do `@arqel/ai` no
 * FieldRegistry do `@arqel/ui`, lazily.
 *
 *   import '@arqel/ai/register';
 *
 * Os component names (`AiTextInput`, `AiTranslateInput`,
 * `AiSelectInput`) batem com o que o PHP retorna em `getComponent()`.
 * Apps podem re-registrar seus próprios componentes sob a mesma chave
 * para sobrescrever o default.
 *
 * Os componentes têm shapes de props específicos do domínio; o
 * registry espera um adapter genérico `{ field, value }`, então cada
 * um é envolvido aqui.
 */

import type { FieldSchema } from '@arqel/types/fields';
import { registerField } from '@arqel/ui/form';
import { type ComponentType, lazy, type ReactElement } from 'react';
import type {
  AiExtractInputFieldProps,
  AiExtractInputProps,
  AiExtractValue,
} from './AiExtractInput.js';
import type { AiSelectInputFieldProps, AiSelectInputProps } from './AiSelectInput.js';
import type { AiTextInputFieldProps, AiTextInputProps } from './AiTextInput.js';
import type {
  AiTranslateInputFieldProps,
  AiTranslateInputProps,
  AiTranslateValue,
} from './AiTranslateInput.js';

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

function adaptToAiTranslateInput(
  Component: ComponentType<AiTranslateInputProps>,
): ComponentType<RegistryFieldProps> {
  return function AiTranslateInputAdapter(registryProps: RegistryFieldProps): ReactElement {
    const { field, value, onChange, resource, csrfToken } = registryProps;
    const fieldProps = (field as unknown as { props?: AiTranslateInputFieldProps }).props;
    // O FieldRegistry tipa `onChange` como `(value: string) => void`,
    // mas este field emite um `Record<string, string>`; o cast é
    // compatível em runtime e mantém o registry agnóstico ao shape.
    const onChangeAdapted =
      onChange !== undefined
        ? (next: AiTranslateValue) => {
            (onChange as unknown as (v: AiTranslateValue) => void)(next);
          }
        : undefined;

    const objectValue: AiTranslateValue | null =
      value !== null && typeof value === 'object' && !Array.isArray(value)
        ? (value as AiTranslateValue)
        : null;

    return (
      <Component
        name={field.name}
        value={objectValue}
        props={fieldProps}
        {...(onChangeAdapted !== undefined ? { onChange: onChangeAdapted } : {})}
        {...(resource !== undefined ? { resource } : {})}
        field={field.name}
        {...(csrfToken !== undefined ? { csrfToken } : {})}
      />
    );
  };
}

function adaptToAiSelectInput(
  Component: ComponentType<AiSelectInputProps>,
): ComponentType<RegistryFieldProps> {
  return function AiSelectInputAdapter(registryProps: RegistryFieldProps): ReactElement {
    const { field, value, onChange, resource, formData, csrfToken } = registryProps;
    const fieldProps = (field as unknown as { props?: AiSelectInputFieldProps }).props;
    // O FieldRegistry tipa `onChange` como `(value: string) => void`,
    // mas o select pode emitir `null` ao limpar a seleção; o cast é
    // compatível em runtime com o registry genérico.
    const onChangeAdapted =
      onChange !== undefined
        ? (next: string | null) => {
            (onChange as unknown as (v: string | null) => void)(next);
          }
        : undefined;

    const stringValue: string | null = typeof value === 'string' && value !== '' ? value : null;

    return (
      <Component
        name={field.name}
        value={stringValue}
        props={fieldProps}
        {...(onChangeAdapted !== undefined ? { onChange: onChangeAdapted } : {})}
        {...(resource !== undefined ? { resource } : {})}
        field={field.name}
        {...(formData !== undefined ? { formData } : {})}
        {...(csrfToken !== undefined ? { csrfToken } : {})}
      />
    );
  };
}

function adaptToAiExtractInput(
  Component: ComponentType<AiExtractInputProps>,
): ComponentType<RegistryFieldProps> {
  return function AiExtractInputAdapter(registryProps: RegistryFieldProps): ReactElement {
    const { field, value, onChange, resource, formData, csrfToken } = registryProps;
    const fieldProps = (field as unknown as { props?: AiExtractInputFieldProps }).props;
    // O FieldRegistry tipa `onChange` como `(value: string) => void`,
    // mas este field emite um `Record<string, unknown>`; o cast é
    // compatível em runtime e mantém o registry agnóstico ao shape.
    const onChangeAdapted =
      onChange !== undefined
        ? (next: AiExtractValue) => {
            (onChange as unknown as (v: AiExtractValue) => void)(next);
          }
        : undefined;

    const objectValue: AiExtractValue | null =
      value !== null && typeof value === 'object' && !Array.isArray(value)
        ? (value as AiExtractValue)
        : null;

    return (
      <Component
        name={field.name}
        value={objectValue}
        props={fieldProps}
        {...(onChangeAdapted !== undefined ? { onChange: onChangeAdapted } : {})}
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

const LazyAiTranslateInput = lazy(async () => {
  const mod = await import('./AiTranslateInput.js');
  return { default: adaptToAiTranslateInput(mod.AiTranslateInput) };
});

const LazyAiSelectInput = lazy(async () => {
  const mod = await import('./AiSelectInput.js');
  return { default: adaptToAiSelectInput(mod.AiSelectInput) };
});

const LazyAiExtractInput = lazy(async () => {
  const mod = await import('./AiExtractInput.js');
  return { default: adaptToAiExtractInput(mod.AiExtractInput) };
});

registerField('AiTextInput', LazyAiTextInput as unknown as Parameters<typeof registerField>[1]);
registerField(
  'AiTranslateInput',
  LazyAiTranslateInput as unknown as Parameters<typeof registerField>[1],
);
registerField('AiSelectInput', LazyAiSelectInput as unknown as Parameters<typeof registerField>[1]);
registerField(
  'AiExtractInput',
  LazyAiExtractInput as unknown as Parameters<typeof registerField>[1],
);

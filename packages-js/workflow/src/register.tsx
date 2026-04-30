/**
 * Side-effect import that registers the workflow React components into
 * `@arqel/ui`'s FieldRegistry, lazily.
 *
 *   import '@arqel/workflow/register';
 *
 * The component name (`arqel/workflow/StateTransition`) matches the
 * value emitted by `Arqel\Workflow\Fields\StateTransitionField::component()`
 * server-side. Apps may re-register their own component under the same
 * key to override the default.
 *
 * `<StateTransition>` itself takes a custom prop shape (see
 * `StateTransitionProps`); the registry expects `FieldRendererProps`,
 * so we expose a tiny adapter here that maps the registry props onto
 * the apresentational component.
 */

import type { FieldSchema } from '@arqel/types/fields';
import { registerField } from '@arqel/ui/form';
import { type ComponentType, lazy } from 'react';
import type { StateTransitionFieldProps, StateTransitionProps } from './StateTransition.js';

interface RegistryFieldProps {
  field: FieldSchema;
  value: unknown;
}

function adaptToStateTransition(
  Component: ComponentType<StateTransitionProps>,
): ComponentType<RegistryFieldProps> {
  return function StateTransitionAdapter(registryProps: RegistryFieldProps) {
    const { field, value } = registryProps;
    const fieldProps = (field as unknown as { props: StateTransitionFieldProps }).props;
    return <Component name={field.name} value={value} props={fieldProps} />;
  };
}

const LazyStateTransition = lazy(async () => {
  const mod = await import('./StateTransition.js');
  return { default: adaptToStateTransition(mod.StateTransition) };
});

registerField(
  'arqel/workflow/StateTransition',
  LazyStateTransition as unknown as Parameters<typeof registerField>[1],
);

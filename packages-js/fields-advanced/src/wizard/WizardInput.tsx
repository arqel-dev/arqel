import { PlaceholderInput } from '../shared/PlaceholderInput.js';
import type { FieldRendererProps } from '../shared/types.js';

export function WizardInput(props: FieldRendererProps) {
  return <PlaceholderInput {...props} componentName="WizardInput" ticket="FIELDS-ADV-017" />;
}

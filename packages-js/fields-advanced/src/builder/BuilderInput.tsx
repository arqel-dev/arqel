import { PlaceholderInput } from '../shared/PlaceholderInput.js';
import type { FieldRendererProps } from '../shared/types.js';

export function BuilderInput(props: FieldRendererProps) {
  return <PlaceholderInput {...props} componentName="BuilderInput" ticket="FIELDS-ADV-014" />;
}

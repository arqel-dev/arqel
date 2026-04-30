import { PlaceholderInput } from '../shared/PlaceholderInput.js';
import type { FieldRendererProps } from '../shared/types.js';

export function KeyValueInput(props: FieldRendererProps) {
  return <PlaceholderInput {...props} componentName="KeyValueInput" ticket="FIELDS-ADV-015" />;
}

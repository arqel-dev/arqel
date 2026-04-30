import { PlaceholderInput } from '../shared/PlaceholderInput.js';
import type { FieldRendererProps } from '../shared/types.js';

export function CodeInput(props: FieldRendererProps) {
  return <PlaceholderInput {...props} componentName="CodeInput" ticket="FIELDS-ADV-012" />;
}

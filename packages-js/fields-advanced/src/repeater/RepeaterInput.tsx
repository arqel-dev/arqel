import { PlaceholderInput } from '../shared/PlaceholderInput.js';
import type { FieldRendererProps } from '../shared/types.js';

export function RepeaterInput(props: FieldRendererProps) {
  return <PlaceholderInput {...props} componentName="RepeaterInput" ticket="FIELDS-ADV-013" />;
}

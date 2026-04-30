import { PlaceholderInput } from '../shared/PlaceholderInput.js';
import type { FieldRendererProps } from '../shared/types.js';

export function TagsInput(props: FieldRendererProps) {
  return <PlaceholderInput {...props} componentName="TagsInput" ticket="FIELDS-ADV-016" />;
}

import { PlaceholderInput } from '../shared/PlaceholderInput.js';
import type { FieldRendererProps } from '../shared/types.js';

export function RichTextInput(props: FieldRendererProps) {
  return <PlaceholderInput {...props} componentName="RichTextInput" ticket="FIELDS-ADV-002" />;
}

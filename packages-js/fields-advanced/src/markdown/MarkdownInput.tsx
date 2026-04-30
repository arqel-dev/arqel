import { PlaceholderInput } from '../shared/PlaceholderInput.js';
import type { FieldRendererProps } from '../shared/types.js';

export function MarkdownInput(props: FieldRendererProps) {
  return <PlaceholderInput {...props} componentName="MarkdownInput" ticket="FIELDS-ADV-011" />;
}

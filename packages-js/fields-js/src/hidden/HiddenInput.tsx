/**
 * `<HiddenInput>` — `type="hidden"` value carrier.
 *
 * The wrapping `<FieldRenderer>` already skips the visible label when
 * `field.label` is empty/null, so server-side `HiddenField::label(null)`
 * keeps the form layout tight. The component just renders the input.
 */

import type { FieldRendererProps } from '@arqel/ui/form';

export function HiddenInput({ field, value, inputId }: FieldRendererProps) {
  return (
    <input
      id={inputId}
      type="hidden"
      name={field.name}
      value={value === null || value === undefined ? '' : String(value)}
      readOnly
    />
  );
}

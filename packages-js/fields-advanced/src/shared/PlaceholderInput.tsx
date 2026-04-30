/**
 * Internal placeholder used by every advanced-field stub during
 * FIELDS-ADV-018. Each concrete component (FIELDS-ADV-010..017)
 * replaces these stubs with the real Tiptap/CodeMirror/dnd-kit
 * implementations; until then, the placeholder still round-trips the
 * current value back to the server via a hidden input so submitting a
 * form does not silently drop data.
 */

import type { FieldRendererProps } from './types.js';

export interface PlaceholderInputProps extends FieldRendererProps {
  componentName: string;
  ticket: string;
}

export function PlaceholderInput({
  value,
  inputId,
  describedBy,
  disabled,
  componentName,
  ticket,
}: PlaceholderInputProps) {
  const serialized = JSON.stringify(value ?? null);
  return (
    <div
      data-arqel-placeholder={componentName}
      data-arqel-ticket={ticket}
      aria-describedby={describedBy}
      className="rounded border border-dashed p-4 text-sm text-muted-foreground"
    >
      <span>
        {componentName} not yet implemented ({ticket})
      </span>
      <input id={inputId} type="hidden" value={serialized} disabled={disabled} readOnly />
    </div>
  );
}

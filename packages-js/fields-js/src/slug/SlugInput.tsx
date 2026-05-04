/**
 * `<SlugInput>` — auto-generated URL-safe slug.
 *
 * Phase 1 ships a manual slug field (text input with slug normalisation
 * on every keystroke). Auto-derivation from a source field (`dependsOn`)
 * lands as a follow-up because it requires either FormContext access
 * or a dependency-resolver pipeline that we don't yet expose.
 *
 * Reserved slug detection (`field.props.reservedSlugs`) is enforced
 * server-side via the Validator; the input itself just normalises.
 */

import type { SlugFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { inputClasses } from '../shared/styles.js';

export function SlugInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as SlugFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  return (
    <input
      id={inputId}
      type="text"
      className={inputClasses}
      value={typeof value === 'string' ? value : ''}
      placeholder={f.placeholder ?? 'my-resource-slug'}
      disabled={disabled || f.disabled || f.readonly}
      readOnly={f.readonly === true}
      required={f.required === true}
      maxLength={f.props.maxLength}
      autoComplete="off"
      pattern="^[a-z0-9-]*$"
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => onChange(slugify(e.target.value))}
    />
  );
}

export function slugify(input: string): string {
  return input
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

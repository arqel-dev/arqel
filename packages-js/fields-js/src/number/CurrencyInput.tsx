/**
 * `<CurrencyInput>` — number input with currency display when blurred.
 *
 * While focused, the input shows the bare numeric value using the
 * field's `decimalSeparator` (so a `pt_BR`/`de`/`fr` user sees and
 * edits `1234,56`, not `1234.56`). Typing is parsed back through the
 * same separator before `Number()`, so comma-decimal locales can type a
 * value without it collapsing to `NaN`/`null`. On blur, the value is
 * reformatted with the field's `prefix`/`thousandsSeparator`/
 * `decimalSeparator` props (server-emitted from `CurrencyField`).
 */

import type { CurrencyFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { useState } from 'react';
import { inputClasses } from '../shared/styles.js';

export function CurrencyInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as CurrencyFieldSchema;
  // While focused we keep the raw typed string locally so a partial
  // entry (`1234,` / `1234,5`) survives re-renders; a controlled numeric
  // value would otherwise drop the trailing decimal mark mid-typing.
  const [draft, setDraft] = useState<string | null>(null);
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;

  const numeric =
    typeof value === 'number' ? value : value === '' || value == null ? null : Number(value);
  const decimals = f.props.decimals ?? 2;
  const decimalSeparator = f.props.decimalSeparator ?? '.';
  const focused = draft !== null;

  const formatted =
    numeric === null || Number.isNaN(numeric)
      ? ''
      : `${f.props.prefix}${formatNumber(numeric, decimals, f.props.thousandsSeparator, decimalSeparator)}${f.props.suffix ?? ''}`;

  const display = focused ? draft : formatted;

  return (
    <input
      id={inputId}
      // `text` (not `number`) while focused so comma-decimal locales can
      // type their decimal mark — a numeric input only accepts '.'.
      type="text"
      inputMode="decimal"
      className={inputClasses}
      value={display}
      placeholder={f.placeholder ?? undefined}
      disabled={isDisabled}
      readOnly={f.readonly === true}
      required={f.required === true}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onFocus={() => setDraft(numeric === null ? '' : toEditable(numeric, decimalSeparator))}
      onBlur={() => setDraft(null)}
      onChange={(e) => {
        setDraft(e.target.value);
        onChange(parseEditable(e.target.value, decimalSeparator));
      }}
    />
  );
}

/**
 * Render a number as an editable string using the field's decimal
 * separator (e.g. `1234.56` -> `1234,56` for a comma-decimal locale).
 * Grouping separators are intentionally dropped while editing.
 */
function toEditable(num: number, decimal: string): string {
  const s = String(num);
  return decimal === '.' ? s : s.replace('.', decimal);
}

/**
 * Parse what the user typed back into a number, honouring the field's
 * decimal separator. Grouping characters are stripped so partially
 * formatted input (`1.234,5`) still round-trips. Returns `null` for
 * empty / unparseable input.
 */
function parseEditable(raw: string, decimal: string): number | null {
  if (raw.trim() === '') {
    return null;
  }
  let normalised = raw;
  if (decimal !== '.') {
    // Drop everything that is not a digit, sign, or the decimal mark,
    // then swap the decimal mark for '.' so Number() can parse it.
    const escaped = decimal.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    normalised = raw.replace(new RegExp(`[^0-9${escaped}+-]`, 'g'), '').replace(decimal, '.');
  }
  const num = Number(normalised);
  return Number.isNaN(num) ? null : num;
}

function formatNumber(num: number, decimals: number, thousands: string, decimal: string): string {
  const fixed = num.toFixed(decimals);
  const [whole, frac] = fixed.split('.');
  const safeWhole = (whole ?? '0').replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
  return frac ? `${safeWhole}${decimal}${frac}` : safeWhole;
}

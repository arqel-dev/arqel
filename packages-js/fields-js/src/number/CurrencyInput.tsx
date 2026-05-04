/**
 * `<CurrencyInput>` — number input with currency display when blurred.
 *
 * While focused, the input shows the raw numeric value (decimal point
 * normalised). On blur, the value is reformatted via Intl.NumberFormat
 * using the field's `prefix`/`thousandsSeparator`/`decimalSeparator`
 * props (server-emitted from `CurrencyField`).
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
  const [focused, setFocused] = useState(false);
  const hasError = errors !== undefined && errors.length > 0;
  const isDisabled = disabled || f.disabled || f.readonly;

  const numeric =
    typeof value === 'number' ? value : value === '' || value == null ? null : Number(value);
  const decimals = f.props.decimals ?? 2;

  const formatted =
    numeric === null || Number.isNaN(numeric)
      ? ''
      : `${f.props.prefix}${formatNumber(numeric, decimals, f.props.thousandsSeparator, f.props.decimalSeparator)}${f.props.suffix ?? ''}`;

  const display = focused ? (numeric === null ? '' : String(numeric)) : formatted;

  return (
    <input
      id={inputId}
      type={focused ? 'number' : 'text'}
      inputMode="decimal"
      className={inputClasses}
      value={display}
      placeholder={f.placeholder ?? undefined}
      disabled={isDisabled}
      readOnly={f.readonly === true}
      required={f.required === true}
      min={f.props.min}
      max={f.props.max}
      step={f.props.step ?? 10 ** -decimals}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onFocus={() => setFocused(true)}
      onBlur={() => setFocused(false)}
      onChange={(e) => {
        const raw = e.target.value;
        if (raw === '') {
          onChange(null);
          return;
        }
        const num = Number(raw);
        onChange(Number.isNaN(num) ? null : num);
      }}
    />
  );
}

function formatNumber(num: number, decimals: number, thousands: string, decimal: string): string {
  const fixed = num.toFixed(decimals);
  const [whole, frac] = fixed.split('.');
  const safeWhole = (whole ?? '0').replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
  return frac ? `${safeWhole}${decimal}${frac}` : safeWhole;
}

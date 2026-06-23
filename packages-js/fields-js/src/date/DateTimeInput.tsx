import type { DateTimeFieldSchema } from '@arqel-dev/types/fields';
import type { FieldRendererProps } from '@arqel-dev/ui/form';
import { inputClasses } from '../shared/styles.js';

/**
 * Detect whether a value string carries an absolute instant — i.e. an
 * explicit UTC `Z` or a numeric `±HH:MM` offset. A bare wall-clock string
 * (`2024-01-02T03:04`) has no zone and is shown as-is.
 */
function hasZone(value: string): boolean {
  return /[zZ]$|[+-]\d{2}:?\d{2}$/.test(value.trim());
}

/**
 * Render an absolute `Date` as the `datetime-local` wall-clock string
 * (`YYYY-MM-DDTHH:mm[:ss]`) observed in `timeZone`. Uses `Intl` parts so
 * the conversion honours DST without pulling in a date library. Falls back
 * to the runtime zone if `Intl` rejects the zone.
 */
function toZonedLocalInput(date: Date, timeZone: string, withSeconds: boolean): string {
  const options: Intl.DateTimeFormatOptions = {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    ...(withSeconds ? { second: '2-digit' } : {}),
  };
  let parts: Intl.DateTimeFormatPart[];
  try {
    parts = new Intl.DateTimeFormat('en-CA', { ...options, timeZone }).formatToParts(date);
  } catch {
    parts = new Intl.DateTimeFormat('en-CA', options).formatToParts(date);
  }
  const get = (type: Intl.DateTimeFormatPartTypes): string =>
    parts.find((p) => p.type === type)?.value ?? '00';
  // `Intl` may emit a literal `24` for midnight in some engines; normalise.
  const hour = get('hour') === '24' ? '00' : get('hour');
  const date_ = `${get('year')}-${get('month')}-${get('day')}`;
  const time = withSeconds
    ? `${hour}:${get('minute')}:${get('second')}`
    : `${hour}:${get('minute')}`;
  return `${date_}T${time}`;
}

/**
 * Inverse of {@link toZonedLocalInput}: interpret a wall-clock string as a
 * local time in `timeZone` and return the equivalent absolute UTC ISO
 * instant, so the edited value round-trips to the server in the same zone
 * the field declared. Computed by measuring the zone's offset at that
 * instant via `Intl` (DST-aware) and subtracting it.
 */
function zonedLocalInputToIso(wallClock: string, timeZone: string): string {
  // Treat the wall-clock as if it were UTC, then correct by the zone offset.
  const asUtc = new Date(`${wallClock}Z`);
  if (Number.isNaN(asUtc.getTime())) return wallClock;
  const offsetMs = zoneOffsetMs(asUtc, timeZone);
  return new Date(asUtc.getTime() - offsetMs).toISOString();
}

/** Offset (ms) of `timeZone` from UTC at the given instant, DST-aware. */
function zoneOffsetMs(date: Date, timeZone: string): number {
  try {
    const dtf = new Intl.DateTimeFormat('en-US', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    });
    const parts = dtf.formatToParts(date);
    const get = (type: Intl.DateTimeFormatPartTypes): number =>
      Number(parts.find((p) => p.type === type)?.value ?? '0');
    const hour = get('hour') === 24 ? 0 : get('hour');
    const asIfUtc = Date.UTC(
      get('year'),
      get('month') - 1,
      get('day'),
      hour,
      get('minute'),
      get('second'),
    );
    return asIfUtc - date.getTime();
  } catch {
    return 0;
  }
}

export function DateTimeInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const f = field as DateTimeFieldSchema;
  const hasError = errors !== undefined && errors.length > 0;
  // `timezone` is server-emitted from `DateTimeField->timezone(...)`. When set
  // and the incoming value is an absolute instant, the native datetime-local
  // control — which always renders in the *browser* zone — would otherwise show
  // the wrong wall-clock time. Convert into the declared zone for display and
  // back to a UTC instant on edit so the stored moment is preserved.
  const timeZone = f.props.timezone;
  const withSeconds = f.props.seconds === true;

  const raw = typeof value === 'string' ? value : '';
  let display = raw;
  if (timeZone !== undefined && timeZone !== '' && raw !== '' && hasZone(raw)) {
    const parsed = new Date(raw);
    if (!Number.isNaN(parsed.getTime())) {
      display = toZonedLocalInput(parsed, timeZone, withSeconds);
    }
  }

  return (
    <input
      id={inputId}
      type="datetime-local"
      className={inputClasses}
      value={display}
      disabled={disabled || f.disabled || f.readonly}
      readOnly={f.readonly === true}
      required={f.required === true}
      min={f.props.minDate}
      max={f.props.maxDate}
      step={withSeconds ? 1 : undefined}
      aria-invalid={hasError || undefined}
      aria-describedby={describedBy}
      onChange={(e) => {
        const next = e.target.value;
        if (next === '') {
          onChange(null);
          return;
        }
        // Re-anchor the edited wall-clock to the declared zone so the value
        // sent back is an absolute instant, not a browser-local string.
        if (timeZone !== undefined && timeZone !== '') {
          onChange(zonedLocalInputToIso(next, timeZone));
          return;
        }
        onChange(next);
      }}
    />
  );
}

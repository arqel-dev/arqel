/**
 * `<WizardInput>` — multi-step form layout with progress indicator.
 *
 * React-side counterpart of `Arqel\FieldsAdvanced\Types\WizardField`
 * (FIELDS-ADV-017, scoped). Reads the following props verbatim from
 * the PHP-emitted schema:
 *
 *   - `steps`        : array<{ name, label, icon?, schema }>
 *   - `persistInUrl` : boolean — sync current step to `window.location.hash`
 *   - `skippable`    : boolean — allow non-linear navigation via the
 *                      progress header and bypass per-step validation
 *
 * ## Scope (FIELDS-ADV-017 — narrowed)
 *
 * The original spec calls for React 19.2 `<Activity>` to preserve
 * per-step state without remounting subtrees, plus Inertia partial
 * reload validation. `<Activity>` requires careful tree placement and
 * is overkill for this scaffold-level component. State always
 * re-renders on step change here. Inertia partial-reload validation is
 * deferred to the wider form-integration follow-up.
 *
 * Sub-field rendering follows the simple dispatcher pattern from
 * `RepeaterInput.tsx`. The whole form data is parent-controlled via
 * `value` + `onChange`; each step writes back as `{ ...value, [name]: v }`.
 */

import { useEffect, useId, useMemo, useState } from 'react';
import type { FieldRendererProps } from '../shared/types.js';

interface SubFieldSchema {
  name: string;
  type: string;
  label?: string | undefined;
  required?: boolean | undefined;
  options?:
    | ReadonlyArray<{ value: string | number; label: string }>
    | Record<string, string>
    | undefined;
  placeholder?: string | undefined;
}

interface WizardStep {
  name: string;
  label: string;
  icon?: string | null;
  schema: SubFieldSchema[];
}

interface WizardProps {
  steps: WizardStep[];
  persistInUrl: boolean;
  skippable: boolean;
}

const SUPPORTED_TYPES = new Set([
  'text',
  'string',
  'number',
  'select',
  'textarea',
  'boolean',
  'checkbox',
]);

const inputClasses =
  'h-9 w-full rounded-sm border border-[var(--input)] ' +
  'bg-background px-3 text-sm text-foreground ' +
  'placeholder:text-muted-foreground ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'disabled:cursor-not-allowed disabled:opacity-50 ' +
  'aria-invalid:border-destructive';

const textareaClasses =
  'w-full rounded-sm border border-[var(--input)] ' +
  'bg-background px-3 py-2 text-sm text-foreground ' +
  'placeholder:text-muted-foreground ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const buttonClasses =
  'inline-flex h-9 items-center justify-center rounded-sm ' +
  'border border-[var(--input)] bg-background ' +
  'px-3 text-sm text-foreground ' +
  'hover:bg-muted ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

const primaryButtonClasses =
  'inline-flex h-9 items-center justify-center rounded-sm ' +
  'border border-primary bg-primary ' +
  'px-3 text-sm text-primary-foreground ' +
  'hover:opacity-90 ' +
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

function readSubField(raw: unknown): SubFieldSchema | null {
  if (typeof raw !== 'object' || raw === null) return null;
  const r = raw as Record<string, unknown>;
  if (typeof r['name'] !== 'string' || typeof r['type'] !== 'string') return null;
  return {
    name: r['name'],
    type: r['type'],
    label: typeof r['label'] === 'string' ? r['label'] : undefined,
    required: typeof r['required'] === 'boolean' ? r['required'] : false,
    options:
      Array.isArray(r['options']) || (typeof r['options'] === 'object' && r['options'] !== null)
        ? (r['options'] as SubFieldSchema['options'])
        : undefined,
    placeholder: typeof r['placeholder'] === 'string' ? r['placeholder'] : undefined,
  };
}

function readStep(raw: unknown): WizardStep | null {
  if (typeof raw !== 'object' || raw === null) return null;
  const r = raw as Record<string, unknown>;
  if (typeof r['name'] !== 'string') return null;
  const schema = Array.isArray(r['schema'])
    ? (r['schema']
        .map(readSubField)
        .filter((s): s is SubFieldSchema => s !== null) as SubFieldSchema[])
    : [];
  return {
    name: r['name'],
    label: typeof r['label'] === 'string' && r['label'] !== '' ? r['label'] : r['name'],
    icon: typeof r['icon'] === 'string' ? r['icon'] : null,
    schema,
  };
}

function readProps(raw: unknown): WizardProps {
  const p = (raw ?? {}) as Partial<Record<keyof WizardProps, unknown>>;
  const steps = Array.isArray(p.steps)
    ? (p.steps.map(readStep).filter((s): s is WizardStep => s !== null) as WizardStep[])
    : [];
  return {
    steps,
    persistInUrl: typeof p.persistInUrl === 'boolean' ? p.persistInUrl : false,
    skippable: typeof p.skippable === 'boolean' ? p.skippable : false,
  };
}

function readHashStepName(): string | null {
  if (typeof window === 'undefined' || !window.location) return null;
  const hash = window.location.hash;
  if (!hash?.startsWith('#step-')) return null;
  return hash.slice('#step-'.length);
}

function writeHashStepName(name: string): void {
  if (typeof window === 'undefined' || !window.location || !window.history) return;
  const next = `#step-${name}`;
  if (window.location.hash === next) return;
  // Use replaceState so back-button isn't polluted with every step toggle.
  try {
    window.history.replaceState(null, '', next);
  } catch {
    // SSR / sandboxed environments — silently fall back to in-memory state.
  }
}

function isEmpty(value: unknown): boolean {
  if (value === undefined || value === null) return true;
  if (typeof value === 'string' && value.trim() === '') return true;
  if (Array.isArray(value) && value.length === 0) return true;
  return false;
}

interface SubFieldInputProps {
  field: SubFieldSchema;
  value: unknown;
  onChange: (next: unknown) => void;
  disabled?: boolean | undefined;
  inputId: string;
}

function SubFieldInput({ field, value, onChange, disabled, inputId }: SubFieldInputProps) {
  const type = field.type;

  if (type === 'textarea') {
    return (
      <textarea
        id={inputId}
        className={textareaClasses}
        value={typeof value === 'string' ? value : String(value ?? '')}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        rows={3}
        placeholder={field.placeholder}
      />
    );
  }

  if (type === 'boolean' || type === 'checkbox') {
    return (
      <input
        id={inputId}
        type="checkbox"
        className="h-4 w-4"
        checked={value === true}
        onChange={(e) => onChange(e.target.checked)}
        disabled={disabled}
      />
    );
  }

  if (type === 'number') {
    return (
      <input
        id={inputId}
        type="number"
        className={inputClasses}
        value={value === null || value === undefined ? '' : String(value)}
        onChange={(e) => {
          const raw = e.target.value;
          onChange(raw === '' ? null : Number(raw));
        }}
        disabled={disabled}
        placeholder={field.placeholder}
      />
    );
  }

  if (type === 'select') {
    const options = (() => {
      if (Array.isArray(field.options)) return field.options;
      if (field.options && typeof field.options === 'object') {
        return Object.entries(field.options as Record<string, string>).map(([v, label]) => ({
          value: v,
          label,
        }));
      }
      return [] as ReadonlyArray<{ value: string | number; label: string }>;
    })();
    return (
      <select
        id={inputId}
        className={inputClasses}
        value={value === null || value === undefined ? '' : String(value)}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
      >
        <option value="">—</option>
        {options.map((opt) => (
          <option key={String(opt.value)} value={String(opt.value)}>
            {opt.label}
          </option>
        ))}
      </select>
    );
  }

  const note = SUPPORTED_TYPES.has(type) ? null : (
    <p className="mt-1 text-xs text-muted-foreground">type {type} not yet supported</p>
  );

  return (
    <div>
      <input
        id={inputId}
        type="text"
        className={inputClasses}
        value={typeof value === 'string' ? value : String(value ?? '')}
        onChange={(e) => onChange(e.target.value)}
        disabled={disabled}
        placeholder={field.placeholder}
      />
      {note}
    </div>
  );
}

export function WizardInput({
  field,
  value,
  onChange,
  errors,
  disabled,
  inputId,
  describedBy,
}: FieldRendererProps) {
  const props = readProps((field as { props?: unknown }).props);
  const hasError = errors !== undefined && errors.length > 0;
  const fallbackId = useId();
  const baseId = inputId ?? fallbackId;
  const titleId = `${baseId}-title`;
  const statusId = `${baseId}-status`;

  const total = props.steps.length;

  const formData = useMemo(() => {
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
      return value as Record<string, unknown>;
    }
    return {} as Record<string, unknown>;
  }, [value]);

  const [currentIndex, setCurrentIndex] = useState<number>(() => {
    if (total === 0) return 0;
    if (props.persistInUrl) {
      const hashName = readHashStepName();
      if (hashName !== null) {
        const idx = props.steps.findIndex((s) => s.name === hashName);
        if (idx >= 0) return idx;
      }
    }
    return 0;
  });

  // Track which steps the user has visited — used to gate non-skippable
  // header clicks (you can re-visit completed steps but cannot leap
  // forward unless `skippable=true`).
  const [visited, setVisited] = useState<Set<number>>(() => new Set([0]));

  const [stepErrors, setStepErrors] = useState<string[]>([]);

  const safeIndex = total === 0 ? 0 : Math.min(Math.max(0, currentIndex), total - 1);
  const currentStep: WizardStep | undefined = props.steps[safeIndex];

  // Sync URL hash with current step when persistInUrl is enabled.
  useEffect(() => {
    if (!props.persistInUrl || !currentStep) return;
    writeHashStepName(currentStep.name);
  }, [props.persistInUrl, currentStep]);

  const validateStep = (idx: number): { valid: boolean; errors: string[] } => {
    const step = props.steps[idx];
    if (!step) return { valid: true, errors: [] };
    const errs: string[] = [];
    for (const sub of step.schema) {
      if (sub.required && isEmpty(formData[sub.name])) {
        errs.push(`${sub.label ?? sub.name} is required.`);
      }
    }
    return { valid: errs.length === 0, errors: errs };
  };

  const goToStep = (target: number, opts: { force?: boolean } = {}): boolean => {
    if (target < 0 || target >= total) return false;
    if (target === safeIndex) return true;

    const movingForward = target > safeIndex;
    if (movingForward && !opts.force && !props.skippable) {
      const v = validateStep(safeIndex);
      if (!v.valid) {
        setStepErrors(v.errors);
        return false;
      }
    }

    setStepErrors([]);
    setCurrentIndex(target);
    setVisited((prev) => {
      const next = new Set(prev);
      next.add(target);
      return next;
    });
    return true;
  };

  const handleHeaderClick = (idx: number) => {
    if (props.skippable) {
      goToStep(idx, { force: true });
      return;
    }
    // Non-skippable: only allow returning to a previously visited step.
    if (visited.has(idx)) {
      goToStep(idx, { force: true });
    }
  };

  const handleNext = () => {
    goToStep(safeIndex + 1);
  };

  const handleBack = () => {
    goToStep(safeIndex - 1, { force: true });
  };

  const handleSubmit = () => {
    if (!props.skippable) {
      const v = validateStep(safeIndex);
      if (!v.valid) {
        setStepErrors(v.errors);
        return;
      }
    }
    setStepErrors([]);
    if (typeof window !== 'undefined' && typeof window.dispatchEvent === 'function') {
      const detail = { name: field.name, value: formData };
      try {
        window.dispatchEvent(new CustomEvent('wizard:submit', { detail }));
      } catch {
        // Older test environments — best-effort.
      }
    }
  };

  const updateSubField = (name: string, next: unknown) => {
    onChange({ ...formData, [name]: next });
  };

  const isFirst = safeIndex === 0;
  const isLast = total === 0 || safeIndex === total - 1;

  if (total === 0) {
    return (
      <div
        id={baseId}
        className="rounded-sm border border-dashed border-[var(--input)] p-4 text-sm text-muted-foreground"
        aria-describedby={describedBy}
      >
        No wizard steps configured.
      </div>
    );
  }

  return (
    <section
      id={baseId}
      aria-labelledby={field.label ? titleId : undefined}
      aria-describedby={describedBy}
      aria-invalid={hasError || undefined}
      className="space-y-4"
    >
      {field.label ? (
        <h2 id={titleId} className="text-sm font-medium text-foreground">
          {field.label}
        </h2>
      ) : null}

      {/* Progress header — explicit role="list" per FIELDS-ADV-017 spec
          (some `list-style: none` resets strip the implicit role in
          Safari/VoiceOver). */}
      {/* biome-ignore lint/a11y/noRedundantRoles: spec mandates explicit role */}
      <ol role="list" className="flex flex-wrap items-center gap-2 pl-0">
        {props.steps.map((step, idx) => {
          const isActive = idx === safeIndex;
          const isCompleted = idx < safeIndex;
          const isVisited = visited.has(idx);
          const reachable = props.skippable || isVisited;

          const baseStepClasses =
            'inline-flex items-center gap-2 rounded-sm border px-3 py-1.5 text-sm';
          const stateClasses = isActive
            ? 'border-primary font-semibold ring-2 ring-ring'
            : isCompleted
              ? 'border-[var(--input)] text-foreground'
              : 'border-[var(--input)] text-muted-foreground';
          const ariaCurrent: 'step' | undefined = isActive ? 'step' : undefined;
          const accessibleLabel = `Step ${idx + 1}: ${step.label}`;

          return (
            <li key={step.name}>
              {reachable ? (
                <button
                  type="button"
                  onClick={() => handleHeaderClick(idx)}
                  aria-current={ariaCurrent}
                  aria-label={accessibleLabel}
                  className={`${baseStepClasses} ${stateClasses} hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring`}
                  disabled={disabled}
                >
                  <span aria-hidden="true">
                    {isCompleted ? '✓ ' : ''}
                    {idx + 1}.
                  </span>
                  <span>{step.label}</span>
                </button>
              ) : (
                // Non-skippable + not yet visited → render as inert span.
                // The active step is always rendered as a <button> above,
                // so dropping aria-current/aria-label here doesn't hide
                // the current-step semantic.
                <span
                  className={`${baseStepClasses} ${stateClasses} cursor-not-allowed`}
                  title={accessibleLabel}
                >
                  <span aria-hidden="true">{idx + 1}.</span>
                  <span>{step.label}</span>
                </span>
              )}
            </li>
          );
        })}
      </ol>

      {/* Live region announcing transitions */}
      <div id={statusId} role="status" aria-live="polite" className="sr-only">
        {currentStep ? `Step ${safeIndex + 1} of ${total}: ${currentStep.label}` : ''}
      </div>

      {/* Step body */}
      {currentStep ? (
        <div className="grid grid-cols-1 gap-3 rounded-sm border border-[var(--input)] bg-background p-4">
          {currentStep.schema.length === 0 ? (
            <p className="text-sm text-muted-foreground">This step has no fields.</p>
          ) : (
            currentStep.schema.map((sub) => {
              const subId = `${baseId}-step-${safeIndex}-${sub.name}`;
              const subLabel = sub.label ?? sub.name;
              return (
                <div key={sub.name} className="grid gap-1">
                  <label htmlFor={subId} className="text-xs font-medium text-muted-foreground">
                    {subLabel}
                    {sub.required ? (
                      <span aria-hidden="true" className="ml-0.5 text-destructive">
                        *
                      </span>
                    ) : null}
                  </label>
                  <SubFieldInput
                    field={sub}
                    value={formData[sub.name]}
                    onChange={(next) => updateSubField(sub.name, next)}
                    disabled={disabled}
                    inputId={subId}
                  />
                </div>
              );
            })
          )}
        </div>
      ) : null}

      {/* Validation errors */}
      {stepErrors.length > 0 ? (
        <div
          role="alert"
          className="rounded-sm border border-destructive bg-destructive/10 p-3 text-sm text-destructive"
        >
          <ul className="list-disc pl-4">
            {stepErrors.map((err) => (
              <li key={err}>{err}</li>
            ))}
          </ul>
        </div>
      ) : null}

      {/* Footer */}
      <div className="flex items-center justify-between gap-2">
        <button
          type="button"
          className={buttonClasses}
          onClick={handleBack}
          disabled={disabled || isFirst}
          aria-label="Back"
        >
          Back
        </button>
        {isLast ? (
          <button
            type="button"
            className={primaryButtonClasses}
            onClick={handleSubmit}
            disabled={disabled}
            aria-label="Submit"
          >
            Submit
          </button>
        ) : (
          <button
            type="button"
            className={primaryButtonClasses}
            onClick={handleNext}
            disabled={disabled}
            aria-label="Next"
          >
            Next
          </button>
        )}
      </div>
    </section>
  );
}

/**
 * `useArqelForm` — wraps Inertia `useForm` with Field-aware defaults.
 *
 * Phase 1 scope: seed initial state from `buildInitialFormState(fields, record)`,
 * proxy through Inertia's API, and stub `validate()`/`validateField()` returning
 * `true` until the Zod bridge ships in HOOKS-002 follow-up.
 */

import { buildInitialFormState } from '@arqel/react/utils';
import type { FieldSchema } from '@arqel/types/fields';
import type { FormDataConvertible } from '@inertiajs/core';
import type { InertiaFormProps } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useMemo } from 'react';

type FormValues = Record<string, FormDataConvertible>;

export interface UseArqelFormOptions<TRecord> {
  fields: readonly FieldSchema[];
  record?: TRecord | null;
  defaults?: FormValues;
}

export interface UseArqelFormExtensions {
  fields: readonly FieldSchema[];
  clientErrors: Record<string, string[]>;
  validate: () => boolean;
  validateField: (name: string) => boolean;
}

export type UseArqelFormResult = InertiaFormProps<FormValues> & UseArqelFormExtensions;

export function useArqelForm<TRecord = Record<string, unknown>>(
  options: UseArqelFormOptions<TRecord>,
): UseArqelFormResult {
  const { fields, record = null, defaults } = options;

  const initial = useMemo<FormValues>(() => {
    const recordObj = record as Record<string, unknown> | null;
    const seed = buildInitialFormState([...fields], recordObj) as FormValues;
    return defaults ? { ...seed, ...defaults } : seed;
  }, [fields, record, defaults]);

  // The Inertia useForm generic causes deep instantiation when the value type
  // is `Record<string, FormDataConvertible>`. The runtime call is fine; we just
  // narrow the result to InertiaFormProps<FormValues> via an unknown bridge.
  const callUseForm = useForm as unknown as (data: FormValues) => InertiaFormProps<FormValues>;
  const form = callUseForm(initial);

  return {
    ...form,
    fields,
    clientErrors: {},
    validate: () => true,
    validateField: () => true,
  };
}

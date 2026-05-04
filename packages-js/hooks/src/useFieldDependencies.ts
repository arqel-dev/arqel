/**
 * `useFieldDependencies` — debounced partial reload when source fields change.
 *
 * Phase 1 scope: detect changes on field values referenced by `dependsOn`
 * descriptors, then `router.reload` with `only: ['fields.<dep>.options']`
 * after a 300 ms debounce.
 */

import type { FieldSchema } from '@arqel-dev/types/fields';
import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export interface UseFieldDependenciesOptions {
  fields: readonly FieldSchema[];
  values: Record<string, unknown>;
  debounceMs?: number;
  onDependencyChange?: (fieldName: string) => void;
}

export function useFieldDependencies(options: UseFieldDependenciesOptions): void {
  const { fields, values, debounceMs = 300, onDependencyChange } = options;
  const lastValuesRef = useRef<Record<string, unknown>>({});
  const timersRef = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());

  useEffect(() => {
    const timers = timersRef.current;

    for (const field of fields) {
      const deps = field.dependsOn;
      if (!deps || deps.length === 0) continue;

      const changed = deps.some((dep) => values[dep] !== lastValuesRef.current[dep]);
      if (!changed) continue;

      const existing = timers.get(field.name);
      if (existing) clearTimeout(existing);

      const handle = setTimeout(() => {
        router.reload({ only: [`fields.${field.name}.options`] });
        onDependencyChange?.(field.name);
        timers.delete(field.name);
      }, debounceMs);

      timers.set(field.name, handle);
    }

    lastValuesRef.current = { ...values };

    return () => {
      for (const handle of timers.values()) clearTimeout(handle);
      timers.clear();
    };
  }, [fields, values, debounceMs, onDependencyChange]);
}

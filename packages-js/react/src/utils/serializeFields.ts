import type { FieldSchema } from '@arqel-dev/types/fields';

/**
 * Build the initial state for a form from a list of FieldSchemas
 * + optional record. Field defaults take precedence; record values
 * override defaults; any field without a value falls back to the
 * type-appropriate empty value.
 *
 * Used by `useArqelForm` (HOOKS-002) to seed Inertia's `useForm`.
 */
export function buildInitialFormState(
  fields: FieldSchema[],
  record?: Record<string, unknown> | null,
): Record<string, unknown> {
  const state: Record<string, unknown> = {};

  for (const field of fields) {
    if (field.visibility.canSee === false) {
      continue;
    }

    const recordValue = record?.[field.name];
    if (recordValue !== undefined) {
      state[field.name] = recordValue;
      continue;
    }

    if (field.defaultValue !== null && field.defaultValue !== undefined) {
      state[field.name] = field.defaultValue;
      continue;
    }

    state[field.name] = emptyValueFor(field);
  }

  return state;
}

/**
 * Returns the type-appropriate empty value for a field, used as
 * the fallback when neither a record nor a default is supplied.
 */
function emptyValueFor(field: FieldSchema): unknown {
  switch (field.type) {
    case 'boolean':
    case 'toggle':
      return false;
    case 'multiSelect':
    case 'hasMany':
      return [];
    case 'number':
    case 'currency':
      return null;
    default:
      return '';
  }
}

/**
 * Reduce a `FieldSchema[]` to a `Record<name, FieldSchema>` for
 * fast lookup by name.
 */
export function indexFieldsByName(fields: FieldSchema[]): Record<string, FieldSchema> {
  const index: Record<string, FieldSchema> = {};
  for (const field of fields) {
    index[field.name] = field;
  }
  return index;
}

/**
 * Filter a list of fields to only those visible in the given
 * context. The serializer already gates on `canSee`; this drills
 * into the per-context `visibility` map.
 */
export function fieldsVisibleIn(
  fields: FieldSchema[],
  context: 'create' | 'edit' | 'detail' | 'table',
): FieldSchema[] {
  return fields.filter((field) => field.visibility[context]);
}

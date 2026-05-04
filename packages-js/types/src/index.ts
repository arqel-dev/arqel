/**
 * Public re-exports for `@arqel-dev/types`.
 *
 * Most apps should import from a subpath
 * (`@arqel-dev/types/fields`, `@arqel-dev/types/resources`, …) so unused
 * shapes are tree-shaken. This barrel exists for convenience —
 * `import type { FieldSchema, ColumnSchema } from '@arqel-dev/types'`.
 */

export type * from './actions.js';
export type * from './fields.js';
export { isFieldType } from './fields.js';
export type * from './forms.js';
export { isFieldEntry, isLayoutEntry, resolveFieldEntry } from './forms.js';
export type * from './inertia.js';
export type * from './resources.js';
export type * from './tables.js';

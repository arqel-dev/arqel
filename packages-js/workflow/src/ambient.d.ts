/**
 * Ambient module declarations for cross-package imports that resolve at
 * runtime via tsup-emitted `.js` files but ship `.d.ts` only after the
 * upstream package's DTS build succeeds. We re-export the minimal
 * surface we use so the workflow package typechecks in isolation.
 *
 * If/when `@arqel-dev/ui` and `@arqel-dev/types` ship DTS reliably, these
 * shims become redundant — feel free to remove them.
 */

declare module '@arqel-dev/ui/form' {
  import type { ComponentType } from 'react';
  export type FieldComponent = ComponentType<unknown>;
  export function registerField(name: string, component: FieldComponent): void;
}

declare module '@arqel-dev/types/fields' {
  export interface FieldSchema {
    name: string;
    type: string;
    component?: string;
    [key: string]: unknown;
  }
}

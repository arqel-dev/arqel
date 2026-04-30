/**
 * Ambient module declarations so the `ai` package typechecks in
 * isolation. `@arqel/ui/form` is consumed only from `register.tsx`,
 * a side-effect module — we declare the minimal `registerField`
 * signature here. Drop this shim once `@arqel/ui` ships DTS reliably.
 */

declare module '@arqel/ui/form' {
  import type { ComponentType } from 'react';
  export type FieldComponent = ComponentType<unknown>;
  export function registerField(name: string, component: FieldComponent): void;
}

declare module '@arqel/types/fields' {
  export interface FieldSchema {
    name: string;
    type: string;
    component?: string;
    [key: string]: unknown;
  }
}

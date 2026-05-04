/**
 * `FieldRegistry` — name → React component mapping for FieldRenderer.
 *
 * `@arqel-dev/fields` (FIELDS-JS-*) calls `registerField` at module init to
 * plug rich inputs in; until then FieldRenderer falls back to the
 * native HTML primitives in `nativeFields.tsx`. Registration is global
 * by design — apps can override Arqel defaults by re-registering with
 * the same name.
 */

import type { ComponentType } from 'react';
import type { FieldRendererProps } from './FieldRenderer.js';

export type FieldComponent = ComponentType<FieldRendererProps>;

const registry = new Map<string, FieldComponent>();

export function registerField(name: string, component: FieldComponent): void {
  registry.set(name, component);
}

export function getFieldComponent(name: string): FieldComponent | undefined {
  return registry.get(name);
}

export function unregisterField(name: string): void {
  registry.delete(name);
}

export function getRegisteredFields(): string[] {
  return Array.from(registry.keys()).sort();
}

/** Test-only helper. */
export function clearFieldRegistry(): void {
  registry.clear();
}

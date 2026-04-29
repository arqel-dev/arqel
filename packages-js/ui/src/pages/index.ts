/**
 * Built-in Inertia page registry for `arqel::*`.
 *
 * `createArqelApp({ pages })` from `@arqel/react/inertia` accepts
 * one or more `PageRegistry` records — merge `arqelPages` first so
 * user-defined pages can override per-resource:
 *
 * ```ts
 * import { arqelPages } from '@arqel/ui/pages';
 * import { createArqelApp } from '@arqel/react/inertia';
 *
 * const userPages = import.meta.glob('./Pages/**\/*.tsx');
 *
 * createArqelApp({
 *   pages: { ...arqelPages, ...userPages },
 * });
 * ```
 *
 * Names match what `ResourceController` emits via
 * `Inertia::render('arqel::index', ...)`.
 */

import type { ComponentType } from 'react';

type LazyPage = () => Promise<{ default: ComponentType<unknown> }>;

export const arqelPages: Record<string, LazyPage> = {
  'arqel::index': () =>
    import('./ArqelIndexPage.js') as Promise<{ default: ComponentType<unknown> }>,
  'arqel::create': () =>
    import('./ArqelCreatePage.js') as Promise<{ default: ComponentType<unknown> }>,
  'arqel::edit': () => import('./ArqelEditPage.js') as Promise<{ default: ComponentType<unknown> }>,
  'arqel::show': () => import('./ArqelShowPage.js') as Promise<{ default: ComponentType<unknown> }>,
};

export { default as ArqelCreatePage } from './ArqelCreatePage.js';
export { default as ArqelEditPage } from './ArqelEditPage.js';
export { default as ArqelIndexPage } from './ArqelIndexPage.js';
export { default as ArqelShowPage } from './ArqelShowPage.js';

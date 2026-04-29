/**
 * @arqel/hooks — Reusable React hooks for Arqel admin panels.
 *
 * Re-exports each hook so consumers can `import { useResource } from '@arqel/hooks'`
 * or use subpath imports (`import { useResource } from '@arqel/hooks/useResource'`)
 * for maximal tree-shaking.
 */

export type { UseActionResult } from './useAction.js';
export { useAction } from './useAction.js';
export type { UseArqelFormOptions, UseArqelFormResult } from './useArqelForm.js';
export { useArqelForm } from './useArqelForm.js';
export type { Breakpoint } from './useBreakpoint.js';
export { useBreakpoint } from './useBreakpoint.js';
export { useCanAccess } from './useCanAccess.js';
export type { UseFieldDependenciesOptions } from './useFieldDependencies.js';
export { useFieldDependencies } from './useFieldDependencies.js';
export type { UseFlashOptions, UseFlashResult } from './useFlash.js';
export { useFlash } from './useFlash.js';
export type { UseNavigationResult } from './useNavigation.js';
export { useNavigation } from './useNavigation.js';
export { useArqelOptimistic } from './useOptimistic.js';
export type { UseResourceResult } from './useResource.js';
export { useResource } from './useResource.js';
export type { TableSort, UseTableOptions, UseTableResult } from './useTable.js';
export { useTable } from './useTable.js';

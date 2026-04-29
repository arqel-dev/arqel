/**
 * @arqel/ui — Structural React components for Arqel admin panels.
 *
 * Top-level barrel re-exports the most-used primitives. Subpath imports
 * (`@arqel/ui/action`, `@arqel/ui/auth`) are recommended for tree-shaking.
 */

export type { ButtonProps } from './action/Button.js';
export { Button, buttonVariants } from './action/Button.js';
export type { CanAccessProps } from './auth/CanAccess.js';
export { CanAccess } from './auth/CanAccess.js';

export { cn } from './utils/cn.js';

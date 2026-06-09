/**
 * Re-export of the single source of truth (issue #236). This is the
 * SAME hook the `@arqel-dev/ui` shell uses, so both operate on one
 * context. The returned value exposes both `resolved` and the
 * `resolvedTheme` alias for backward-compat.
 */
export { useTheme } from '@arqel-dev/react/providers';

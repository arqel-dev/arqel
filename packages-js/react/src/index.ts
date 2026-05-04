/**
 * Public re-exports for `@arqel-dev/react`. Most apps should import
 * from a subpath (`@arqel-dev/react/inertia`, `@arqel-dev/react/providers`,
 * …) so unused modules tree-shake.
 */

export * from './context/index.js';
export * from './devtools/index.js';
export * from './inertia/index.js';
export * from './providers/index.js';
export * from './utils/index.js';

/**
 * Package version exposed to the DevTools hook. Kept manually in sync
 * with `package.json` because tsup does not inject `process.env`-style
 * replacements by default.
 */
export const ARQEL_REACT_VERSION = '0.8.0-rc.1';

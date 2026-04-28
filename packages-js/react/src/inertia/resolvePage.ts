import type { ComponentType } from 'react';

export type LazyPage = () => Promise<{ default: ComponentType<unknown> }>;
export type PageRegistry = Record<string, LazyPage>;

/**
 * Wraps `import.meta.glob`-style records into an Inertia `resolve`
 * callback. Pages whose key matches the requested name resolve to
 * the registered loader; missing pages throw a clear error so
 * users see the typo at boot rather than a blank screen.
 *
 * Apps typically construct the user pages registry like:
 *
 *   const pages = import.meta.glob<{ default: ComponentType }>(
 *     './Pages/**\/*.tsx',
 *   );
 *   createArqelApp({ pages });
 *
 * Built-in `arqel::*` pages are registered later by `@arqel/ui`
 * (UI-001+) — at that point we'll merge the two registries inside
 * `createArqelApp`.
 */
export function resolveArqelPage(
  registries: PageRegistry[],
  name: string,
): Promise<{ default: ComponentType<unknown> }> {
  for (const registry of registries) {
    const loader = matchLoader(registry, name);
    if (loader !== undefined) {
      return loader();
    }
  }

  throw new Error(
    `resolveArqelPage: no page found for [${name}]. Make sure the file exists at the expected path or that it is registered through createArqelApp({ pages }).`,
  );
}

function matchLoader(registry: PageRegistry, name: string): LazyPage | undefined {
  // Exact match first.
  if (registry[name] !== undefined) {
    return registry[name];
  }

  // Fall back to "ends with .tsx" Vite-style globs by stripping
  // the leading "./" + matching against the page key suffix.
  const suffixCandidates = [
    `./${name}.tsx`,
    `./${name}.jsx`,
    `./Pages/${name}.tsx`,
    `./Pages/${name}.jsx`,
  ];

  for (const candidate of suffixCandidates) {
    const loader = registry[candidate];
    if (loader !== undefined) {
      return loader;
    }
  }

  return undefined;
}

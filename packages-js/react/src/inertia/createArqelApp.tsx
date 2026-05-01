import { createInertiaApp, router as inertiaRouter } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';

import { installDevToolsHook, installInertiaBridge } from '../devtools/index.js';
import type { InertiaRouterLike } from '../devtools/inertia-bridge.js';
import { ArqelProvider } from '../providers/ArqelProvider.js';
import type { Theme } from '../providers/ThemeProvider.js';
import { type PageRegistry, resolveArqelPage } from './resolvePage.js';

/**
 * Version reported to the DevTools hook. Kept in sync with `package.json`.
 * (Duplicated here to avoid pulling the root barrel into the inertia entry.)
 */
const ARQEL_REACT_VERSION = '0.8.0-rc.1';

export interface ArqelAppOptions {
  /** Customise the document title. Default: `${page} — ${appName}`. */
  title?: (title: string) => string;
  /** App name for the default title callback. */
  appName?: string;
  /** User-defined page registries (e.g. from `import.meta.glob`). */
  pages?: PageRegistry;
  /** Optional layout wrapper rendered around every Arqel page. */
  layout?: (page: ReactNode) => ReactNode;
  /** Default theme passed to `<ArqelProvider>`. */
  defaultTheme?: Theme;
  /** Inertia progress bar config. `true` enables defaults; `false` disables. */
  progress?: boolean | { color?: string; delay?: number };
}

/**
 * Bootstrap an Arqel + Inertia app. Wraps `createInertiaApp` with
 * sensible defaults: panel/theme providers, a built-in page
 * resolver, optional layout slot, and SSR-friendly hydrate/create
 * branch.
 *
 * @example
 * ```ts
 * import { createArqelApp } from '@arqel/react/inertia';
 *
 * const userPages = import.meta.glob('./Pages/**\/*.tsx');
 *
 * createArqelApp({
 *   appName: 'Acme Admin',
 *   pages: userPages,
 * });
 * ```
 */
export async function createArqelApp(options: ArqelAppOptions = {}): Promise<unknown> {
  const {
    title,
    appName = 'Arqel',
    pages = {},
    layout,
    defaultTheme = 'system',
    progress = true,
  } = options;

  // Install the DevTools hook before Inertia boots. The call is a no-op
  // in production builds (Vite's `import.meta.env.DEV` is `false`), so
  // shipped apps never expose `window.__ARQEL_DEVTOOLS_HOOK__`.
  const devtoolsHook = installDevToolsHook(ARQEL_REACT_VERSION);
  if (devtoolsHook !== undefined) {
    // Inertia's `router` exposes `on(event, cb)` that returns an
    // unsubscribe — structurally compatible with `InertiaRouterLike`.
    installInertiaBridge(devtoolsHook, inertiaRouter as unknown as InertiaRouterLike);
  }

  return createInertiaApp({
    title: title ?? ((current: string) => (current ? `${current} — ${appName}` : appName)),
    progress: progress === false ? false : progress === true ? {} : progress,
    resolve: async (name) => {
      const module = await resolveArqelPage([pages], name);
      const Component = module.default as ComponentType<unknown> & {
        layout?: (page: ReactNode) => ReactNode;
      };

      if (Component.layout === undefined && layout !== undefined) {
        Component.layout = layout;
      }

      return module;
    },
    setup({ el, App, props }) {
      const node = (
        <ArqelProvider defaultTheme={defaultTheme}>
          <App {...props} />
        </ArqelProvider>
      );

      // SSR: hydrate the server-rendered markup; CSR: create root.
      if (el.hasChildNodes()) {
        hydrateRoot(el, node);
      } else {
        createRoot(el).render(node);
      }
    },
  });
}

import { createInertiaApp } from '@inertiajs/react';
import type { ComponentType, ReactNode } from 'react';
import { createRoot, hydrateRoot } from 'react-dom/client';

import { ArqelProvider } from '../providers/ArqelProvider.js';
import type { Theme } from '../providers/ThemeProvider.js';
import { type PageRegistry, resolveArqelPage } from './resolvePage.js';

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

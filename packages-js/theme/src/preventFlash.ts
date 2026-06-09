/**
 * Re-export of the FOUC guard from `@arqel-dev/react/providers`
 * (issue #236 — single source of truth). Shares the `arqel-theme`
 * storage key + `dark` class defaults with {@link ThemeProvider}.
 *
 * Uso (Blade):
 *   <script>{!! \Arqel\Theme\preventFlashScript() !!}</script>
 *
 * Uso direto:
 *   <script dangerouslySetInnerHTML={{ __html: preventFlashScript() }} />
 */
export { type PreventFlashOptions, preventFlashScript } from '@arqel-dev/react/providers';

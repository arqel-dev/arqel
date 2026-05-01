import { copyFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

/**
 * Multi-entry build for Arqel DevTools browser extension.
 *
 * Each `vite build --mode <chrome|firefox>` run produces a self-contained
 * `dist/<browser>/` directory ready to be loaded as an unpacked extension.
 *
 * Entries:
 *  - background        (service worker / event page)
 *  - content-script    (injected on every page; detects Arqel hook)
 *  - devtools          (boot script for the DevTools page)
 *  - panel             (React app rendered inside the DevTools tab)
 */
export default defineConfig(({ mode }) => {
  const browser = mode === 'firefox' ? 'firefox' : 'chrome';
  const outDir = resolve(__dirname, 'dist', browser);

  return {
    build: {
      outDir,
      emptyOutDir: true,
      target: 'es2022',
      sourcemap: true,
      minify: false,
      rollupOptions: {
        input: {
          background: resolve(__dirname, 'src/background.ts'),
          'content-script': resolve(__dirname, 'src/content-script.ts'),
          devtools: resolve(__dirname, 'src/devtools.ts'),
          panel: resolve(__dirname, 'src/panel/index.tsx'),
        },
        output: {
          entryFileNames: '[name].js',
          chunkFileNames: 'chunks/[name]-[hash].js',
          assetFileNames: 'assets/[name][extname]',
          format: 'es',
        },
      },
    },
    plugins: [
      {
        name: 'arqel-devtools-static-assets',
        closeBundle() {
          const root = resolve(__dirname, 'src');
          mkdirSync(resolve(outDir, 'icons'), { recursive: true });

          // Manifest tailored for the target browser.
          const manifestPath = resolve(root, 'manifests', `${browser}.json`);
          const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
          writeFileSync(resolve(outDir, 'manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`);

          // HTML shells.
          copyFileSync(resolve(root, 'devtools.html'), resolve(outDir, 'devtools.html'));
          copyFileSync(resolve(root, 'panel.html'), resolve(outDir, 'panel.html'));

          // Icons (single SVG master used as fallback for all sizes —
          // see scripts/generate-icons.mjs to produce real PNGs).
          const iconSvg = resolve(root, 'icons/icon.svg');
          for (const size of [16, 32, 48, 128]) {
            copyFileSync(iconSvg, resolve(outDir, 'icons', `icon-${size}.png`));
          }
        },
      },
    ],
  };
});

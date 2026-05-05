import { defineConfig } from 'tsup';

export default defineConfig({
  entry: {
    index: 'src/index.ts',
    inertia: 'src/inertia/index.ts',
    providers: 'src/providers/index.ts',
    context: 'src/context/index.ts',
    devtools: 'src/devtools/index.ts',
    utils: 'src/utils/index.ts',
  },
  format: ['esm'],
  dts: true,
  sourcemap: true,
  clean: true,
  treeshake: true,
  splitting: true,
  minify: false,
  external: ['react', 'react-dom', '@inertiajs/react'],
});

import { defineConfig } from 'tsup';

export default defineConfig({
  entry: {
    cli: 'src/cli.tsx',
    index: 'src/index.ts',
  },
  format: ['esm'],
  dts: true,
  sourcemap: true,
  clean: true,
  treeshake: true,
  splitting: false,
  minify: false,
  target: 'node20',
  banner: { js: '#!/usr/bin/env node' },
  external: ['react', 'ink', 'meow', 'chalk'],
});

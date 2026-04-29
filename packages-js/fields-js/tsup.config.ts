import { defineConfig } from 'tsup';

export default defineConfig({
  entry: {
    index: 'src/index.ts',
    register: 'src/register.ts',
    text: 'src/text/index.ts',
    number: 'src/number/index.ts',
    boolean: 'src/boolean/index.ts',
  },
  format: ['esm'],
  dts: true,
  sourcemap: true,
  clean: true,
  treeshake: true,
  splitting: false,
  minify: false,
  external: ['react', 'react-dom', '@arqel/ui', '@arqel/types', '@inertiajs/react'],
});

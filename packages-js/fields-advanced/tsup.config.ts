import { defineConfig } from 'tsup';

export default defineConfig({
  entry: {
    index: 'src/index.ts',
    register: 'src/register.ts',
    'rich-text': 'src/rich-text/index.ts',
    markdown: 'src/markdown/index.ts',
    code: 'src/code/index.ts',
    repeater: 'src/repeater/index.ts',
    builder: 'src/builder/index.ts',
    'key-value': 'src/key-value/index.ts',
    tags: 'src/tags/index.ts',
    wizard: 'src/wizard/index.ts',
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

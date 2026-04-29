import { defineConfig } from 'tsup';

export default defineConfig({
  entry: {
    index: 'src/index.ts',
    register: 'src/register.ts',
    text: 'src/text/index.ts',
    number: 'src/number/index.ts',
    boolean: 'src/boolean/index.ts',
    select: 'src/select/index.ts',
    relationship: 'src/relationship/index.ts',
    date: 'src/date/index.ts',
    file: 'src/file/index.ts',
    slug: 'src/slug/index.ts',
    color: 'src/color/index.ts',
    hidden: 'src/hidden/index.ts',
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

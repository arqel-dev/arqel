import { defineConfig } from 'tsup';

export default defineConfig({
  entry: {
    index: 'src/index.ts',
    shell: 'src/shell/index.ts',
    resource: 'src/resource/index.ts',
    table: 'src/table/index.ts',
    form: 'src/form/index.ts',
    action: 'src/action/index.ts',
    auth: 'src/auth/index.ts',
    flash: 'src/flash/index.ts',
    utility: 'src/utility/index.ts',
    utils: 'src/utils/index.ts',
  },
  format: ['esm'],
  dts: true,
  sourcemap: true,
  clean: true,
  treeshake: true,
  splitting: false,
  minify: false,
  external: [
    'react',
    'react-dom',
    '@inertiajs/react',
    '@arqel/react',
    '@arqel/hooks',
    '@arqel/types',
    '@base-ui-components/react',
    '@tanstack/react-table',
    'lucide-react',
  ],
});

import { defineConfig } from 'tsup';

export default defineConfig({
  entry: {
    index: 'src/index.ts',
    fields: 'src/fields.ts',
    resources: 'src/resources.ts',
    tables: 'src/tables.ts',
    forms: 'src/forms.ts',
    actions: 'src/actions.ts',
    inertia: 'src/inertia.ts',
  },
  format: ['esm'],
  dts: true,
  sourcemap: true,
  clean: true,
  treeshake: true,
  splitting: false,
  minify: false,
});

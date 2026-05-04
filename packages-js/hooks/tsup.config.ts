import { defineConfig } from 'tsup';

export default defineConfig({
  entry: {
    index: 'src/index.ts',
    useResource: 'src/useResource.ts',
    useArqelForm: 'src/useArqelForm.ts',
    useCanAccess: 'src/useCanAccess.ts',
    useFlash: 'src/useFlash.ts',
    useTable: 'src/useTable.ts',
    useAction: 'src/useAction.ts',
    useFieldDependencies: 'src/useFieldDependencies.ts',
    useNavigation: 'src/useNavigation.ts',
    useBreakpoint: 'src/useBreakpoint.ts',
    useOptimistic: 'src/useOptimistic.ts',
    useResourceUpdates: 'src/useResourceUpdates.ts',
  },
  format: ['esm'],
  dts: true,
  sourcemap: true,
  clean: true,
  treeshake: true,
  splitting: false,
  minify: false,
  external: ['react', 'react-dom', '@inertiajs/react', '@arqel-dev/react', '@arqel-dev/types'],
});

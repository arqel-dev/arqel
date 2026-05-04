import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'public/build',
    manifest: true,
    rollupOptions: {
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./resources/js/__tests__/setup.ts'],
    include: ['resources/js/**/*.test.{ts,tsx}'],
  },
});

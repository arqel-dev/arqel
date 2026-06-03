import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.tsx'],
      refresh: false,
    }),
    react(),
    tailwindcss(),
  ],
  server: {
    watch: {
      // Vite's chokidar watcher exhausts inotify limits on Linux when
      // it follows the entire `vendor/` and `node_modules/` trees.
      // Excluding them keeps HMR snappy and prevents the dev server
      // from crashing with `ENOSPC` on long sessions.
      ignored: ['**/vendor/**', '**/node_modules/**', '**/storage/**', '**/.git/**'],
    },
  },
});

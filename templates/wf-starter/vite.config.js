import { defineConfig } from 'vite';

export default defineConfig({
  root: '.',
  build: {
    outDir: 'dist',
    manifest: true,
    rollupOptions: {
      input: 'src/js/app.js',
    },
  },
  server: {
    host: '127.0.0.1',
    port: 5176,
    strictPort: true,
  },
});

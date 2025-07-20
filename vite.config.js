import { resolve } from 'path';

export default {
  build: {
    cssCodeSplit: false,
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        app: resolve(__dirname, 'src/main.js')
      },
      output: {
        assetFileNames: 'app.css',
        entryFileNames: 'app.js',
        format: 'iife',
        globals: {
          // Keep window global scope
        }
      }
    }
  },
  server: {
    port: 5173,
    open: false
  }
};

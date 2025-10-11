import { resolve } from 'path';
import { defineConfig } from 'vite';

const devPort = Number(process.env.VITE_DEV_PORT || process.env.PORT || 5176);
const hmrPort = Number(process.env.VITE_HMR_PORT || devPort);

export default defineConfig({
  root: __dirname,
  define: {
    '__APP_ENV__': JSON.stringify(process.env.NODE_ENV || 'development'),
  },
  server: {
    port: devPort,
    strictPort: true,
    host: 'localhost',
    cors: true,
    headers: {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    },
    hmr: {
      protocol: 'ws',
      host: 'localhost',
      port: hmrPort,
      clientPort: hmrPort,
    },
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path,
      }
    }
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        'js/app.js': resolve(__dirname, 'src/entries/app.js'),
        'js/admin-dashboard.js': resolve(__dirname, 'src/entries/admin-dashboard.js'),
        'js/admin-inventory.js': resolve(__dirname, 'src/entries/admin-inventory.js'),
        'js/admin-settings.js': resolve(__dirname, 'src/entries/admin-settings.js'),
        'js/admin-db-status.js': resolve(__dirname, 'src/entries/admin-db-status.js'),
        'js/header-bootstrap.js': resolve(__dirname, 'src/entries/header-bootstrap.js'),
        'js/admin-email-settings.js': resolve(__dirname, 'src/entries/admin-email-settings.js'),
        'js/admin-customers.js': resolve(__dirname, 'src/entries/admin-customers.js'),
        'js/admin-account-settings.js': resolve(__dirname, 'src/entries/admin-account-settings.js'),
        'js/admin-orders.js': resolve(__dirname, 'src/entries/admin-orders.js'),
        'js/pos.js': resolve(__dirname, 'src/entries/pos.js'),
        'js/admin-cost-breakdown.js': resolve(__dirname, 'src/entries/admin-cost-breakdown.js'),
        'js/help-documentation.js': resolve(__dirname, 'src/entries/help-documentation.js'),
      },
    }
  },
});

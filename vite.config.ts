import { resolve } from 'path';
import { defineConfig } from 'vite';

const devPort = Number(process.env.VITE_DEV_PORT || process.env.PORT || 5173);
const hmrPort = Number(process.env.VITE_HMR_PORT || devPort);
const isProd = (process.env.NODE_ENV === 'production');

export default defineConfig({
  root: __dirname,
  base: isProd ? '/dist/' : '/',
  resolve: {
    extensions: ['.mjs', '.js', '.mts', '.ts', '.jsx', '.tsx', '.json'],
    alias: {
      '/images': resolve(__dirname, 'images'),
    },
  },
  define: {
    'process.env': {},
    'global': 'window',
    '__APP_ENV__': JSON.stringify(process.env.NODE_ENV || 'development'),
  },
  esbuild: isProd ? { drop: ['debugger'] } : {},
  server: {
    port: devPort,
    strictPort: true,
    host: '127.0.0.1',
    cors: true,
    headers: {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    },
    hmr: {
      host: 'localhost',
      port: devPort
    },
    watch: {
      ignored: ['**/logs/**', '**/scripts/*.log']
    },
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        configure: (proxy, _options) => {
          proxy.on('error', (err, _req, _res) => {
            console.log('proxy error', err);
          });
          proxy.on('proxyReq', (proxyReq, req, _res) => {
            console.log('Sending Request to the Target:', req.method, req.url);
          });
          proxy.on('proxyRes', (proxyRes, req, _res) => {
            console.log('Received Response from the Target:', proxyRes.statusCode, req.url);
          });
        },
      },

      '/functions': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/logout.php': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/login.php': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/register.php': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/privacy': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/terms': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/policy': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/images': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/pos': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/receipt': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/receipt.php': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/admin': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/dist': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
      '/build-assets': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      }
    }
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        'index': resolve(__dirname, 'index.html'),
        'js/main.js': resolve(__dirname, 'src/entries/main.tsx'),
        'css/admin-core.css': resolve(__dirname, 'src/styles/entries/admin-core.css'),
        'css/public-core.css': resolve(__dirname, 'src/styles/entries/public-core.css'),
        'css/embed-core.css': resolve(__dirname, 'src/styles/entries/embed-core.css'),
      },
      output: {
        manualChunks: (id) => {
          if (id.includes('node_modules')) {
            return 'vendor';
          }
          if (
            id.includes('/src/context/') ||
            id.includes('/src/hooks/') ||
            id.includes('/src/core/') ||
            id.includes('/src/types/')
          ) {
            return 'vendor-app-core';
          }
        }
      }
    }
  },
});

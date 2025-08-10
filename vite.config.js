import { resolve } from 'path';
import { globSync } from 'glob';
import fullReload from 'vite-plugin-full-reload';
import { defineConfig } from 'vite';



export default defineConfig({
    root: __dirname,
    plugins: [],
    define: {
        '__APP_ENV__': JSON.stringify('development'),
    },
    server: {
        hmr: false,
    },
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                app: resolve(__dirname, 'js/app.js'),
                'admin-dashboard': resolve(__dirname, 'js/admin-dashboard.js'),
                'admin-inventory': resolve(__dirname, 'js/admin-inventory.js'),
            },
        }
    },

});

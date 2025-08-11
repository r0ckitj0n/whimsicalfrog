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
                // Keep manifest keys stable while sourcing from src entries
                'js/app.js': resolve(__dirname, 'src/entries/app.js'),
                'js/admin-dashboard.js': resolve(__dirname, 'src/entries/admin-dashboard.js'),
                'js/admin-inventory.js': resolve(__dirname, 'src/entries/admin-inventory.js'),
            },
        }
    },

});

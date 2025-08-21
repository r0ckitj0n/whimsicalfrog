import { resolve } from 'path';
import { globSync } from 'glob';
import fullReload from 'vite-plugin-full-reload';
import { defineConfig } from 'vite';



export default defineConfig({
    root: __dirname,
    plugins: [
        {
            name: 'wf-cors-headers',
            configureServer(server) {
                server.middlewares.use((req, res, next) => {
                    res.setHeader('Access-Control-Allow-Origin', '*');
                    res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
                    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                    if (req.method === 'OPTIONS') {
                        res.statusCode = 204;
                        return res.end();
                    }
                    next();
                });
            },
        },
    ],
    define: {
        '__APP_ENV__': JSON.stringify('development'),
    },
    server: {
        // Ensure dev server runs on a stable port that matches our PHP helper hot origin
        port: 5199,
        strictPort: true,
        host: true, // listen on all addresses (IPv4/IPv6)
        // Use HTTP; we proxy via PHP to avoid CORS/TLS issues
        // https: false by default
        cors: true,
        headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization',
        },
        // Enable HMR so @vite/client can inject CSS and handle updates
        hmr: {
            protocol: 'ws',
            host: 'localhost',
            port: 5199,
            clientPort: 5199,
        },
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

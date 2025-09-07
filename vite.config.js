import { resolve } from 'path';
import { globSync } from 'glob';
import fullReload from 'vite-plugin-full-reload';
import { defineConfig } from 'vite';



const devPort = Number(process.env.VITE_DEV_PORT || process.env.PORT || 5176);
const hmrPort = Number(process.env.VITE_HMR_PORT || devPort);

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
        port: devPort,
        strictPort: true,
        host: 'localhost', // use localhost so cookies are same-site with backend on localhost
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
            port: hmrPort,
            clientPort: hmrPort,
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
                'js/admin-settings.js': resolve(__dirname, 'src/entries/admin-settings.js'),
                'js/admin-db-status.js': resolve(__dirname, 'src/entries/admin-db-status.js'),
                'js/header-bootstrap.js': resolve(__dirname, 'src/entries/header-bootstrap.js'),
                'js/admin-email-settings.js': resolve(__dirname, 'src/entries/admin-email-settings.js'),
                'js/admin-customers.js': resolve(__dirname, 'src/entries/admin-customers.js'),
                'js/admin-account-settings.js': resolve(__dirname, 'src/entries/admin-account-settings.js'),
                'js/admin-orders.js': resolve(__dirname, 'src/entries/admin-orders.js'),
                'js/admin-pos.js': resolve(__dirname, 'src/entries/admin-pos.js'),
                'js/admin-cost-breakdown.js': resolve(__dirname, 'src/entries/admin-cost-breakdown.js'),
            },
        }
    },

});

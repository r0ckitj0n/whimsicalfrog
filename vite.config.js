import { resolve } from 'path';
import { glob } from 'glob';
import fullReload from 'vite-plugin-full-reload';

// Find all top-level JS files in the js/ directory
const entryPoints = glob.sync('js/*.js').reduce((acc, path) => {
    const name = path.split('/').pop().replace('.js', '');
    acc[name] = resolve(__dirname, path);
    return acc;
}, {});

export default {
    plugins: [
        fullReload(['includes/**/*', 'sections/**/*', '*.php'])
    ],
    root: __dirname,
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: entryPoints
        }
    },
    server: {
        port: 5173,
        open: false,
        strictPort: true,
    }
};

/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './index.php',
        './*.php',
        './*.html',
        './api/**/*.php',
        './includes/**/*.php',
        './src/**/*.{js,ts,jsx,tsx}',
        './templates/**/*.php',
        './sections/**/*.php',
        './components/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    primary: {
                        DEFAULT: 'var(--brand-primary)',
                        bg: 'var(--brand-primary-bg)',
                        border: 'var(--brand-primary-border)',
                    },
                    secondary: {
                        DEFAULT: 'var(--brand-secondary)',
                        bg: 'var(--brand-secondary-bg)',
                        border: 'var(--brand-secondary-border)',
                    },
                    accent: {
                        DEFAULT: 'var(--brand-accent)',
                        bg: 'var(--brand-accent-bg)',
                        border: 'var(--brand-accent-border)',
                    },
                    error: {
                        DEFAULT: 'var(--brand-error)',
                        bg: 'var(--brand-error-bg)',
                        border: 'var(--brand-error-border)',
                    },
                    warning: {
                        DEFAULT: 'var(--brand-warning)',
                        bg: 'var(--brand-warning-bg)',
                        border: 'var(--brand-warning-border)',
                    },
                },
            },
            fontSize: {
                'fluid-h1': 'clamp(2rem, 5vw, 3rem)',
                'fluid-h2': 'clamp(1.5rem, 4vw, 2.25rem)',
                'fluid-h3': 'clamp(1.25rem, 3vw, 1.75rem)',
                'fluid-h4': 'clamp(1.125rem, 2vw, 1.5rem)',
            },
            fontFamily: {
                merienda: ['var(--font-primary)', 'cursive'],
                nunito: ['var(--font-secondary)', 'sans-serif'],
            },
            zIndex: {
                'negative': 'var(--wf-z-negative)',
                'base': 'var(--wf-z-base)',
                'elevated': 'var(--wf-z-elevated)',
                'sticky': 'var(--wf-z-sticky)',
                'admin-overlay': 'var(--wf-z-admin-overlay)',
                'admin-content': 'var(--wf-z-admin-content)',
                'backdrop': 'var(--wf-z-backdrop)',
                'modal': 'var(--wf-z-modal)',
                'modal-elevated': 'var(--wf-z-modal-elevated)',
                'toast': 'var(--wf-z-toast)',
                'topmost': 'var(--wf-z-topmost)',
                'cursor': 'var(--wf-z-cursor)',
            },
        },
    },
    plugins: [],
};

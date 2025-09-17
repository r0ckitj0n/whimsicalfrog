# WF Starter Kit

This starter kit packages the WhimsicalFrog engineering guidelines into a minimal template you can copy to begin new projects.

- Vite-managed CSS/JS
- PHP helpers for dev/prod asset injection, logging, DB singleton, secret store, and CSRF
- Basic page skeleton with header/footer partials
- CI guard scripts and workflow templates

## Quick Start

1) Create a new project folder using the script in the parent repo:

```
./scripts/create-wf-project.sh /path/to/new-project --name "My New Project"
```

2) In the new project folder:

```
npm install
npm run dev
# In another terminal
php -S localhost:8080 -t .
```

Open http://localhost:8080 and you should see the starter page. Vite HMR runs at http://127.0.0.1:5176.

## Contents

- `index.php` + `partials/` (header/footer)
- `includes/` (vite helper, logger, csrf, secret store)
- `api/` (config + health endpoint)
- `src/` (JS/CSS)
- `vite.config.js`, `package.json`, `.env.example`, `.gitignore`
- `.github/workflows/ci.yml`
- `scripts/guard-templates-css.mjs`, `scripts/check-orphaned-css.mjs`

Refer to `documentation/WF_ENGINEERING_GUIDELINES.md` in your parent repo for the full standards.

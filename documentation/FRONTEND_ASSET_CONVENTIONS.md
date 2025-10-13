# FRONTEND_ASSET_CONVENTIONS

- **Client ESM (browser)**
  - Location: `src/`
  - Extension: `.js` (ES Modules)
  - Bundled via Vite. Do not use `.mjs` for browser code.
  - Entrypoints referenced by PHP helpers: e.g., `src/entries/app.js`, `src/entries/header-bootstrap.js`.

- **Node/CLI tooling**
  - Location: `scripts/` (subfolders allowed: `dev/`, `guards/`, `maintenance/`, etc.)
  - Extension: `.mjs` (ES Modules under Node)
  - Invoked by `npm run` scripts and CI/utility workflows.

- **Vite/PHP integration**
  - `partials/header.php` emits `src/entries/*.js` during dev and `vite('js/*.js')` in prod.
  - `includes/vite_helper.php` resolves entries keyed by `.js` sources and the manifest.
  - Do not rename client entries to `.mjs` without updating these references.

- **General rules**
  - Prefer semantic, modular imports; avoid global side-effects.
  - Keep browser code free of Node-only APIs.
  - Scripts intended for local maintenance, audits, guards, and migrations should live under `scripts/`.
  - Template scaffolding under `templates/` may include its own `scripts/` for starters; keep as-is.

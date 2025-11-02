## How to run Vite (Dev and Build)

Use the Vite pipeline for all CSS/JS during development and production.

### Development

```bash
npm install           # first-time setup
npm run dev -- --host # start Vite dev server (network-visible)
# Local:   http://localhost:5176/
# Network: http://<your-ip>:5176/
```

- Launch your app via the normal PHP host (nginx/apache/php -S). Vite injects assets when `partials/header.php` calls `vite('js/app.js')`.
- Hot Module Replacement (HMR) will live-reload CSS/JS changes in `src/styles/` and `src/js/`.

### Linting / Guards

```bash
npm run guard:templates:css  # ensure no inline style= or legacy CSS links in live templates
npm run guard:brand-colors   # no legacy green hexes remaining
npm run guard:fonts          # prefer CSS variables for font-family
```

### Production build

```bash
npm run build
```

This writes versioned assets to `.vite/` and configures injection through the PHP Vite helper. Avoid committing raw CSS/JS outside `src/` and avoid `<link rel="stylesheet">` in templates.

# WhimsicalFrog Engineering Guidelines (Reusable for New Projects)

This document consolidates the standards and preferences established on WhimsicalFrog into a single, reusable guide. Use this as the baseline for new projects to ensure consistent architecture, tooling, and conventions.


## Core Principles

- Source-of-truth CSS and JS are managed with Vite. No legacy <script> or <link rel="stylesheet"> tags in templates; assets are injected via a helper.
- Prefer source-level CSS fixes and consolidation over ad-hoc overrides. Eliminate duplicate and attribute-based selectors; favor semantic classes.
- No inline JavaScript or inline styles in PHP/HTML templates. Use data-action attributes strictly for behavior (JS), not for styling.
- Centralize cross-cutting concerns: logging, database connections, secrets, notifications, and cart interactions.
- Keep the repository clean and predictable: consistent directory structure, quarantine backups/duplicates, archive unused code (do not delete).


## Repository Organization

- documentation/: All Markdown documentation (keep README.md at root; everything else in documentation/)
- scripts/: All dev/ops scripts (shell, Node, PHP utilities). Use subfolders (e.g., scripts/dev/, scripts/css/, scripts/db/)
- backups/:
  - backups/duplicates/: Quarantine copies with trailing suffixes (e.g., file " 2", " 3", *.bak), preserving paths
  - backups/unused_styles/: Archive unused CSS rules/files instead of deleting
  - backups/sql/: Archive SQL files not required at runtime
  - backups/tooltips/: Tooltip snapshot JSONs (help-tooltips-*.json). All tooltip exports must live here; restore scripts search this path first.
- reports/: Automated reports (e.g., asset audits, CSS consolidation output)
- public assets/images: Keep originals; avoid duplication across dist/

### Markdown Naming and Location Standards

- All project Markdown files live under `documentation/` with optional subfolders (e.g., `documentation/technical/`, `documentation/frontend/`, `documentation/includes/`).
- Filenames are UPPER_SNAKE_CASE using underscores, never hyphens. Examples:
  - `WF_ENGINEERING_GUIDELINES.md`
  - `MODAL_SYSTEM_FLOW_DOCUMENTATION.md`
  - `IMAGE_NAMING_POLICY_AND_MIGRATION.md`
- Prefer descriptive, scoped names; group by subfolder when helpful.
- Keep `README.md` at the repository root and also allow `documentation/README.md` as the docs index.
- When linking internally, use paths starting with `documentation/` (not `docs/`).


## Frontend Standards

### Build and Asset Pipeline (Vite)

- All CSS/JS is built and served by Vite.
- Development server convention:
  - Vite dev: http://127.0.0.1:5176 (HMR)
  - PHP dev server: http://localhost:8080
- Asset injection in PHP via a helper (e.g., `includes/vite_helper.php`):
  - Dev mode: injects absolute dev-server URLs directly (127.0.0.1:5176); proxy fallback allowed
  - Prod mode: reads manifest, injects JS plus recursively collects CSS from manifest imports to ensure complete styles
- Never hardcode <script src> or <link href> in templates; rely on the helper for both dev and prod.

### CSS Conventions

- Prefer source-level fixes. Remove duplicate rules with a canonical map when consolidating; keep a single source of truth per component.
- Use semantic classes for styling (e.g., `.btn--qty`, `.input--qty`, `.btn--primary`), not attribute selectors like `[data-action=...]` or `button[onclick=...]`.
- Global variables in a single place (e.g., `src/styles/variables.css`), including brand colors and button palette (e.g., `--button-bg-primary`, `--button-text-primary`).
- Fonts centralized (e.g., Merienda primary, Nunito secondary). Expose `--font-primary` and `--font-secondary` and utilities like `.wf-brand-font-secondary`.
- Room/shop/pages share componentized styles (e.g., `src/styles/components/*.css`). Avoid page-specific one-offs where possible.
- Remove legacy and unused selectors via automated checks; archive to `backups/unused_styles/`.

### JavaScript Conventions

- No inline event handlers. Use delegated listeners on document or stable containers. `data-action` is strictly for behavior targeting.
- Do not use attribute selectors in CSS for `data-action`. Style with semantic classes; JS toggles those classes as needed.
- Standardize notifications through a single API (e.g., `wfNotifications`, exposed via `window.showNotification` helpers).
- Standardize cart interactions via a single Cart system (e.g., `window.WF_Cart`) with clear ready/initialization hooks.
- Modularize features into Vite modules, dynamically imported per page from a page loader (e.g., `src/js/app.js`).
- Avoid `.on*` assignments; always use `addEventListener`.

### Modal and Popup System

- Modal visibility is controlled by toggling a `.show` class on the overlay/container. The CSS must map `.room-modal-overlay.show` → visible.
- Closing behaviors: overlay click, ESC key, and a primary back button. Remove redundant top-right X when back is available.
- Scroll lock: add `modal-open` to both `<html>` and `<body>` when any modal is open. Release only when no other modals are open.
- Z-index layering:
  - Room modal overlay below detailed item modal and popup when required
  - Detailed item modal uses a very high z-index and remains visible above overlays
  - `#itemPopup` toggles `.in-room-modal` when triggered inside room modal to pick higher z-index styles
- Global popup is included once site-wide (e.g., via `partials/footer.php`) and not embedded in modal content.

### Event/Init Order and Globals

- Establish global aliases (e.g., `window.WhimsicalFrog` and `window.wf`) before emitting framework ready events (e.g., `core:ready`).
- Ensure any legacy modules calling `wf.addModule(...)` run after aliases exist.
- Avoid auto-show side effects; never call `this.show()` during open/construct.


## Backend Standards (PHP)

### Database Access

- Centralize DB configuration and connections (e.g., `api/config.php` with a `Database` singleton like `Database::getInstance()`).
- Do not instantiate `PDO` directly in endpoints. Do not include `includes/database.php` from endpoints; require `api/config.php` and use the singleton.
- Test/live connection helpers should also go through central factory methods (e.g., `Database::createConnection(...)` if available).

### Logging

- Use a centralized `Logger` (e.g., `includes/logger.php`) with methods like `Logger::exception()`, `Logger::error()`, `Logger::info()`, `Logger::debug()`.
- Replace all `error_log()` calls in application code with `Logger` calls. Preserve response shapes; do not leak sensitive errors to clients.
- Add structured context in logs (e.g., endpoint name, action, filename, user id, order id).

### Secrets and Configuration

- Use a DB-backed encrypted secret store (e.g., `includes/secret_store.php`) keyed by a filesystem secret (e.g., `config/secret.key`), which is gitignored.
- Never hardcode secrets/API keys in code or templates. Read them from the secret store.
- Implement CSRF protection on admin forms/actions and avoid exposing secrets in client-side code.

### API Design

- Normalize JSON responses; set appropriate headers; use a hardened API client on the frontend.
- For dev, default API base to `http://localhost:8080` and prefer absolute `/api/...` paths in the browser.
- Add debug flags and structured diagnostics sparingly; guard behind dev checks.


## Admin Pages and Settings

- Use a canonical admin settings template (e.g., `sections/admin_settings.php`); keep `admin/admin_settings.php` as a thin delegator to avoid duplication.
- Load admin JS via Vite-managed entries (e.g., `src/entries/admin-settings.js`), lazy-load heavy modules on interaction.
- Migrate all inline `onclick/onchange/onsubmit` handlers to delegated handlers in a Vite module (e.g., `src/js/admin-settings.js`).
- Keep JSON data blocks (`<script type="application/json">`) where appropriate, consumed by modules.
- For admin styles, prefer semantic classes and a small, focused stylesheet (e.g., `src/styles/admin-*.css`).


## Checkout, Cart, and Payment

- Cart API is centralized on `window.WF_Cart`. Avoid legacy singletons or direct DOM-writes for pricing.
- Guard against race conditions: wait for cart readiness on pages that depend on it (e.g., checkout/payment).
- On payment pages, raise z-index for checkout containers if global overlays exist.
- Debugging hooks: client-side warnings when SKU/price is missing; server-side logs annotate per-item pricing.


## Images and Fallbacks

- Replace inline `onerror` attributes with centralized image fallback logic using `data-fallback` or `data-fallback-src` attributes.
- Keep fallback utilities in server-side helpers (e.g., `includes/image_helper.php`) and use consistent attributes in components.


## CI/CD and Tooling

- Linting:
  - ESLint: warn on `console.log` (allow `console.warn`/`console.error`), enforce module style
  - Stylelint: enforce valid, deduplicated CSS
  - PHP lint: `php -l` on changed files
- Guards:
  - `scripts/guard-templates-css.mjs` fails builds on legacy `<link rel="stylesheet">`, `href="*.css"` in templates, or inline style attributes (allow-list emails/backups)
  - `scripts/check-orphaned-css.mjs` to detect unreferenced CSS; archive to `backups/unused_styles/`
  - `scripts/dev/guard-backups-staged.mjs` blocks staging backup-like files outside `backups/`, and enforces tooltip snapshots under `backups/tooltips/`
- Pre-commit hook:
  - Block duplicate-suffixed files (" 2", " 3", `*.bak`) outside `backups/duplicates/`
- Dev scripts and monitors should remain in repo (e.g., do not archive `scripts/start_servers.sh` or `scripts/server_monitor.sh`).
- Composer vendor/ is dev-only and should be ignored by Git and excluded from deployments.


## Development Workflow

1) Start servers
- PHP dev server at :8080 (router-aware if needed)
- Vite dev server at :5176 (ensure hot file or helper detects dev)

2) Build assets
- `npm run dev` for HMR, `npm run build` for production

3) Coding conventions
- Add/modify styles only in `src/styles/` and import through `src/styles/main.css`
- Add/modify scripts only in `src/js/` or `src/modules/`, imported from the page loader (`src/js/app.js`)
- For admin pages, prefer per-page entry modules under `src/entries/` and lazy-load heavy features

4) Testing
- Visual smoke test key pages (home, room main, shop, login, admin)
- Verify modals open/close, scroll lock, and z-index layering
- Verify cart/checkout flows and pricing calculations

5) Cleanup
- Remove duplicates and archive unused CSS to `backups/unused_styles/`
- Run orphaned-CSS and template CSS guards


## Migration Patterns (for Legacy Projects)

- Inline JS → delegated handlers in Vite modules (use `data-action` only for behavior)
- `[data-action]` CSS selectors → semantic class names
- Legacy script/style tags in templates → helper-injected Vite bundles
- Direct PDO usage → `Database::getInstance()` from a centralized config include
- `error_log()` → `Logger` with structured context
- `onerror` image fallbacks → centralized attributes
- Dynamic CSS endpoints → consider migrating values to CSS variables; keep server-generated CSS only when truly dynamic


## Checklists

### New Page Checklist
- Add markup without inline scripts/styles
- Add page-specific module (if needed) and import via `src/js/app.js`
- Add component styles under `src/styles/components/` and import in `src/styles/main.css`
- Verify assets load via Vite helper in PHP template

### New Admin Feature Checklist
- Wire UI with semantic classes and `data-action` for behavior
- Implement a dedicated module (e.g., `src/js/admin-*.js`) with delegated listeners
- Persist settings via `/api/...` endpoints with CSRF protection
- Add minimal admin styles under `src/styles/admin-*.css`


## Defaults and Conventions Summary

- Dev base URLs: PHP :8080, Vite :5176
- Asset loading via `includes/vite_helper.php` (dev → absolute HMR URLs; prod → manifest with recursive CSS collection)
- Global notifications: `wfNotifications`
- Cart: `window.WF_Cart`
- Secrets: `includes/secret_store.php` with filesystem key at `config/secret.key` (gitignored)
- DB: `api/config.php` + `Database::getInstance()`
- Logging: `includes/logger.php` (`Logger::*`), no `error_log()`
- Backups: quarantine duplicates under `backups/duplicates/`; archive unused CSS under `backups/unused_styles/`


---

Adopt this guide verbatim for new projects and adjust only where requirements differ. If you’d like a matching CI template and starter helper files (vite helper, logger, DB bootstrap), I can generate a reusable starter kit next.

# Project Guardrails Starter Kit

Use this starter kit to bootstrap a new project with the exact conventions, guardrails, and hygiene policies established on WhimsicalFrog. Copy this file (and referenced helpers) into the new repository before writing any code.

---

## 1. Core Philosophy

- **Vite-managed assets**: All CSS/JS lives under `src/` and is compiled by Vite. Never hand-include `<script>` or `<link rel="stylesheet">` tags in templates.
- **Source-first styling**: Fix CSS at the source. Avoid one-off overrides and attribute selectors. Semantic classes only.
- **Zero inline code**: Inline styles and inline JS handlers (`onclick`, `onchange`, etc.) are prohibited. Use delegated event listeners in modules.
- **Centralized services**: One logger, one database bootstrap, one secrets store, one cart/notification system.
- **Clean repo layout**: Enforce predictable directories, quarantine duplicates, and archive unused artifacts instead of deleting them.

---

## 2. Repository Layout Blueprint

- **Root**
  - `README.md` (project overview)
  - `documentation/` (all Markdown docs; filenames in `UPPER_SNAKE_CASE.md`)
    - `documentation/README.md` (docs index)
    - Subfolders such as `documentation/technical/`, `documentation/frontend/`
  - `scripts/` (ops + tooling; use subfolders like `scripts/dev/`, `scripts/maintenance/`)
  - `backups/`
    - `backups/duplicates/` for files with trailing " 2" / " 3" / `*.bak` / `*.ffs_lock`
    - `backups/unused_styles/` for archived CSS
    - `backups/sql/` for SQL not needed at runtime
    - `backups/env/` and `backups/text/` for swept `.env*` and `.txt`
  - `reports/` (automated audits)
  - `partials/`, `includes/`, `components/`, etc. as needed for the application

---

## 3. Naming Conventions & Policies

- **Markdown**: All docs except the root `README.md` live under `documentation/` with uppercase snake-case names (`MY_DOC.md`). CI/pre-commit fails on hyphenated names.
- **Images**: Prefer kebab-case (e.g., `custom-item.webp`). Background images may retain underscores until loaders are refactored.
- **Scripts**: No shell/PHP helper scripts at repo root. Place under `scripts/` (or archive to `backups/`).
- **Text files**: `.txt` must live in `backups/text/` or the root `txt/` folder.
- **Duplicate artifacts**: Any file ending with ` 2`, ` 3`, or `.bak` belongs in `backups/duplicates/` (path preserved).
- **Lock files**: FreeFileSync `*.ffs_lock` also belongs in `backups/duplicates/`.

---

## 4. Git Hooks & Automation

1. Copy `.githooks/` into the new repo root.
2. Activate hooks:
   ```bash
   git config core.hooksPath .githooks
   ```
3. Pre-commit hook responsibilities (mirrors CI):
   - Quarantines duplicates, `.env` backups, `.txt`, and legacy `*-old` files into `backups/`
   - Blocks root-level CSS and ops scripts
   - Enforces `.txt` policy and duplicate suffix policy
   - Invokes `lint-staged`
4. Ensure maintenance scripts referenced by the hook exist under `scripts/maintenance/` (`quarantine_duplicates.sh`, `sweep_env_backups.sh`, etc.). Copy them together.

---

## 5. CI & Guard Scripts

- Add workflow(s) under `.github/workflows/` that mirror:
  - Markdown filename guard (fails when docs are not uppercase snake-case)
  - `npm run guard:templates:css` (blocks inline `style=` and legacy CSS includes in live templates)
  - `npm run guard:brand-colors`, `npm run guard:fonts`, and any other lint scripts defined in `package.json`
- Keep `lint-staged` config aligned with pre-commit checks to avoid drift.

---

## 6. Vite Integration

- **Dev server**: run `npm run dev -- --host` on port 5176 (`http://localhost:5176`).
- **Backend dev**: run PHP (or other backend) on `http://localhost:8080`.
- **Helper**: Include a PHP helper like `includes/vite_helper.php` that:
  - Detects dev mode (hot file or env flag) and injects HMR URLs
  - Reads the manifest in production and recursively includes CSS imports
  - Supports overriding dev origin via `WF_VITE_ORIGIN`
- **Templates**: Always call `vite('js/app.js')` (or similar entry) from `partials/header.php`. Never hand-edit `<script>` tags.

---

## 7. CSS Standards

- Source-of-truth in `src/styles/`. Import component sheets into `src/styles/main.css`.
- Semantic class names (e.g., `.btn--primary`, `.input--qty`). No styling on `[data-action]` or `[onclick]` selectors.
- Use CSS variables for brand colors and fonts (`--button-bg-primary`, `--font-primary`).
- Archive unused selectors to `backups/unused_styles/` instead of deleting.
- Scrollbar styles standardized (17px, neutral colors) via shared admin CSS.

---

## 8. JavaScript Standards

- Place entry points under `src/entries/` and shared modules under `src/js/` or `src/modules/`.
- No inline event handlers; rely on delegated listeners keyed by `data-action` attributes.
- Reserve `data-action` strictly for behavior. Styling toggles attach or remove semantic classes.
- Establish globals before firing readiness events (`window.WhimsicalFrog`, `window.wf`, etc.) so legacy consumers can hook in.
- Provide compatibility bridges for legacy systems (e.g., `window.cart` delegating to `window.WF_Cart`).
- Keep modal logic centralized; ensure `.show` class toggles visibility and scroll locking adds/removes `modal-open` on `<html>` and `<body>`.
- Include global popup markup once (e.g., in `partials/footer.php`), with JS toggling `.in-room-modal` when embedded in modals.

---

## 9. Backend Guardrails (PHP)

- **Database**: Route all DB access through a singleton defined in `api/config.php` (e.g., `Database::getInstance()`). No raw `new PDO` calls in endpoints.
- **Logging**: Use `includes/logger.php` and its `Logger::exception/info/error/debug` helpers. Remove `error_log()` from production code.
- **Secrets**: Store API keys and credentials in an encrypted DB-backed secret store (`includes/secret_store.php`) keyed by a filesystem secret (`config/secret.key`, gitignored).
- **API**: Normalize JSON responses, set correct headers, and prefer absolute `/api/...` paths on the frontend. Dev base URL is `http://localhost:8080`.
- **Admin templates**: Use a canonical admin shell (`sections/admin_settings.php`) with thin delegators under `admin/`. Load assets via Vite entry modules only.

---

## 10. Modal, Cart, and Checkout Requirements

- Modal overlays use `.show` class for visibility. Provide keyboard/overlay close behavior.
- Enforce z-index layering so detailed item modals and popups can appear over room modals.
- Cart interactions flow through `window.WF_Cart`; guard against race conditions by awaiting readiness.
- Payment UI raises z-index to sit above global overlays when necessary.
- Client-side warnings surface when items are missing SKU/price; server logs include structured pricing context.

---

## 11. Development Workflow Checklist

1. **Start servers**
   - `npm run dev -- --host` (Vite on :5176)
   - PHP backend on :8080 (use router if needed)
2. **Coding**
   - Add styles in `src/styles/` (imported via `main.css`)
   - Add scripts in `src/js/` / `src/modules/`
   - For admin pages, create per-page entries in `src/entries/` and lazy-load heavy modules
3. **Testing**
   - Smoke test home, room main, shop, login, admin dashboard
   - Verify modal open/close, scroll locking, z-index layering
   - Test cart + checkout flows; confirm receipt handling and post-login cart persistence
4. **Cleanup**
   - Run guard scripts (`npm run guard:templates:css`, orphaned CSS checks)
   - Sweep duplicates or stray files via maintenance scripts
5. **Build**
   - `npm run build` before packaging or deployment

---

## 12. Migration Playbook for Legacy Projects

- Convert inline JS to delegated handlers in Vite modules (`data-action` targets).
- Replace CSS selectors targeting `[data-action]` or inline handlers with semantic classes.
- Swap legacy script/style tags for Vite helper injection.
- Replace direct `PDO` usage and `error_log()` with `Database::getInstance()` and `Logger`.
- Move inline `onerror` image fallbacks to centralized helper attributes.
- Archive dynamic CSS endpoints after migrating values to CSS variables (keep only when truly dynamic).

---

## 13. Quick-Start Actions for a New Repo

1. Copy the following folders/files:
   - `.githooks/`
   - `scripts/` (at least `scripts/maintenance/` and guard scripts)
   - `documentation/PROJECT_GUARDRAILS_STARTER.md` and `documentation/WF_ENGINEERING_GUIDELINES.md`
   - `includes/vite_helper.php`, `includes/logger.php`, `includes/secret_store.php`
   - `partials/header.php`, `partials/footer.php` (or equivalents that load Vite entries)
   - `package.json`, `package-lock.json`, `vite.config.*`, and `postcss.config.cjs`
   - `.github/workflows/` guard workflows (especially docs and template guards)
2. Run `npm install` and `composer install` (if PHP deps are used).
3. Set up the dev secrets key:
   ```bash
   mkdir -p config
   openssl rand -hex 32 > config/secret.key
   chmod 600 config/secret.key
   ```
4. Configure Git hooks via `git config core.hooksPath .githooks`.
5. Start servers (`npm run dev -- --host`, PHP dev server on :8080).
6. Commit the starter guardrails as the first commit in the new repository.

---

Adopt this starter kit verbatim for new projects unless requirements demand otherwise. Pair it with `documentation/WF_ENGINEERING_GUIDELINES.md` for deeper explanations and rationale behind each guardrail.

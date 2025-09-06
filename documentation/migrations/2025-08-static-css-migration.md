# Static/Vite CSS Migration (August 2025)

This document records the migration from the legacy database‑driven CSS system to static CSS assets managed by Vite.

## Summary
- Removed all frontend dynamic CSS loaders and API fetches.
- All pages now rely on static styles bundled via Vite (and regular static CSS in `public/css/` where applicable).
- Deprecated endpoints moved to backups: see `api/_deprecated_css_endpoints/`.

## Affected Frontend Files
- sections/admin_dashboard.php — removed DB CSS loader.
- sections/admin_pos.php — removed global CSS variables fetch and print‑window CSS loader.
- sections/admin_inventory.php — removed DB CSS loader.
- db_web_manager.php — removed two DB CSS loaders (auth + main pages).
- cost_breakdown_manager.php — removed DB CSS loader.
- db_status.php — removed DB CSS loader and fixed head markup.
- register_page.html — removed global CSS variables fetch.

Each removal was replaced with a concise comment: “CSS is managed via static/Vite assets.”

## Backend Endpoints
- `api/css_generator.php` — deprecated. Was returning placeholder content; now moved to backup.
- `api/global_css_rules.php` — deprecated. Was returning placeholder JSON; now moved to backup.

Both files are retained in `api/_deprecated_css_endpoints/` for historical reference and can be safely deleted later.

## Operations and Monitoring
- Grep verification: no remaining frontend references to `api/css_generator.php` or `api/global_css_rules.php`.
- The `css_variables` and `global_css_rules` tables are not used by the frontend anymore. Any admin UI that used them should be considered archived and updated to point to static styles.

## Developer Guidance
- Add or modify styles in the Vite pipeline (see `src/` and Vite config).
- Prefer class‑based selectors for styling; keep `data-action` for JS behavior only.
- Avoid inline `style` or dynamically injected `<style>` unless absolutely necessary.

## Rollback
- If a rollback is required, restore endpoints from `api/_deprecated_css_endpoints/` and reintroduce the loader snippets (not recommended).

## Owner
- Migration executed August 28, 2025 (EDT).

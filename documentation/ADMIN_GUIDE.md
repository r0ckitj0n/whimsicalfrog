# WhimsicalFrog Admin Guide (Current Configuration)

<sub><sup>Last updated: 2025-09-14</sup></sub>

This guide consolidates the current admin architecture, routes, tools, build setup, and conventions. It supersedes older documents that referenced legacy admin entrypoints.

## Table of Contents
- [Routing Overview](#routing-overview)
- [Admin Sections](#admin-sections)
- [Admin Tools](#admin-tools)
- [DB Tools API](#db-tools-api)
- [Build & Vite Entries](#build--vite-entries)
- [Modal & CSS Conventions](#modal--css-conventions)
- [Security & Auth Notes](#security--auth-notes)
- [Maintenance & CI Guards](#maintenance--ci-guards)

---

## Room System Overview
The room pages share a centralized implementation to eliminate duplication and improve maintainability.

Key components:
- `includes/room_helper.php` — server-side rendering and helpers.
- `src/styles/components/room-modal.css`, `room-main.css` — consolidated styles.
- `src/js/room-page.js` (example) — centralized interactions and modal management.

Highlights:
- Database-driven configuration for room content and coordinates.
- Reusable modal and popup logic with consistent z-index and visibility rules.
- Clean template usage for new rooms (see `sections/room_template_clean.php`).

Usage checklist:
- Create `sections/roomN.php` from the clean template and register in navigation.
- Configure room settings and coordinates via admin tools.
- Keep any per-room styling/extensions in dedicated CSS modules under `src/styles/`.

---

## Routing Overview
- Canonical Admin URL: `/admin/`
  - Internally rewritten to `sections/admin_router.php`.
  - Query routing: `/admin/?section=<name>`.
- Legacy `/admin/admin.php` is permanently redirected (301) to `/admin/`.

## Admin Sections
All admin UIs reside under `sections/` and are included via the admin router.

Common sections and their routes:
- Customers: `/admin/?section=customers` → `sections/admin_customers.php`
- Inventory: `/admin/?section=inventory` → `sections/admin_inventory.php`
- Orders: `/admin/?section=orders` → `sections/admin_orders.php`
- POS: `/admin/?section=pos` → `sections/admin_pos.php`
- Reports: `/admin/?section=reports` → `sections/admin_reports.php`
- Marketing: `/admin/?section=marketing` → `sections/admin_marketing.php`
- Settings: `/admin/?section=settings` → `sections/admin_settings.php`
- Categories: `/admin/?section=categories` → `sections/admin_categories.php`
- Secrets: `/admin/?section=secrets` → `sections/admin_secrets.php`
- Dashboard (default): `/admin/` → `sections/admin_dashboard.php`

Embeds for Settings modal iframes:
- Attributes embed: `components/embeds/attributes_embed.php` (renders `sections/admin_inventory.php` with header hidden)
- Categories embed: `components/embeds/categories_embed.php` (renders `sections/admin_categories.php` with header hidden)

## Admin Tools
Admin tools have been migrated to `sections/tools/`:
- Room Config Manager: `/admin/?section=room-config-manager` → `sections/tools/room_config_manager.php`
- Room Map Manager: `/admin/?section=room-map-manager` → `sections/tools/room_map_manager.php`
- Area–Item Mapper: `/admin/?section=area-item-mapper` → `sections/tools/area_item_mapper.php`
- Room Map Editor: `/admin/?section=room-map-editor` → `sections/tools/room_map_editor.php`
- Cost Breakdown Manager: `/admin/?section=cost-breakdown-manager` → `sections/tools/cost_breakdown_manager.php`
- DB Status: `/admin/?section=db-status` → `sections/tools/db_status.php`
- DB Web Manager: `/admin/?section=db-web-manager` → `sections/tools/db_web_manager.php`

Deprecated CLI tool stubs (kept as guidance pages):
- `sections/tools/db_manager.php` and `sections/tools/db_quick.php` recommend the Web Manager and the API.

## DB Tools API
`api/db_tools.php` provides safe, admin-guarded introspection endpoints:
- `action=csrf_token` → returns a CSRF token for mutating actions (e.g., `generate-css`).
- `action=status` → overall status (local/live if permitted).
- `action=version&env=local|live`
- `action=table_counts&env=...`
- `action=db_size&env=...`
- `action=list_tables&env=...`
- `action=describe&env=...&table=TABLE`

Security & permissions:
- Requires admin authentication via `AuthHelper::requireAdmin()`.
- Role-based permissions restrict certain actions (e.g., `describe` and live env access require `superadmin` or `devops`).
- CSRF required for mutating actions (server returns 428 + `X-CSRF-Token` when needed).

The DB Web Manager (`/admin/?section=db-web-manager`) exposes these via UI, with an introspection panel.

## Build & Vite Entries
- All admin JS modules are built via Vite; entries defined in `vite.config.js`.
- Admin modules include (examples):
  - `src/js/admin-dashboard.js`
  - `src/js/admin-inventory.js`
  - `src/js/admin-orders.js`
  - `src/js/admin-db-status.js`
  - `src/js/admin-db-web-manager.js`
- Public app entry: `src/js/app.js` (detects admin routes and avoids loading public-only modules).

## Modal & CSS Conventions
- Use `.show`/`.hidden` classes and set `aria-hidden` appropriately.
- Overlay/stacking:
  - Room modal overlay requires `.show` for visibility.
  - Detailed item modal uses elevated z-index.
  - Popups within room modal use context-aware class toggles.
- Styles consolidated under `src/styles/` and admin-specific under `src/styles/admin-settings.css`.

## Security & Auth Notes
- Admin routes require authentication; redirects handled in `index.php` if visiting `/admin` pages while unauthenticated.
- Sessions initialized in `includes/session.php` with secure cookie defaults.
- Use `AuthHelper::requireAdmin()` for API endpoints.

## Maintenance & CI Guards
- CI (GitHub Actions) pipeline includes:
  - Inline-style guard (`scripts/disallow-inline-styles.mjs`).
  - Template CSS guard.
  - Duplicate/backup guard.
  - ESLint / Stylelint / orphaned CSS checks.
  - Admin router guard (`scripts/dev/guard-admin-router.mjs`) to prevent reintroducing `/admin/admin.php`.

---

This guide reflects the current, clean admin configuration with consolidated tooling and routing. For any historical references or CLI scripts, consult repository history.

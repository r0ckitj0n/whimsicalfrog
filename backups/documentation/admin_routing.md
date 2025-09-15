# Admin Routing Standard

This document defines the canonical routing for admin pages and how section UIs are organized.

## Goals
- Single source of truth per admin section
- Consistent layout, header/navbar spacing, and assets
- Backward-compatible with legacy URLs

## Canonical Entry
- Router: `/admin/` (rewritten to `sections/admin_router.php`)
- Route by query: `/admin/?section=<name>`

Examples
- Inventory: `/admin/?section=inventory`
- Settings: `/admin/?section=settings`
- Reports: `/admin/?section=reports`

## Sections Directory
- All admin section UIs are included by the router from `sections/`.
- Each section file is named `sections/admin_<name>.php`.

Current mappings (router -> sections)
- `customers` → `sections/admin_customers.php`
- `inventory` → `sections/admin_inventory.php`
- `orders` → `sections/admin_orders.php`
- `pos` → `sections/admin_pos.php`
- `reports` → `sections/admin_reports.php`
- `marketing` → `sections/admin_marketing.php`
- `settings` → `sections/admin_settings.php`
- `secrets` → `sections/admin_secrets.php`
- `categories` → `sections/admin_categories.php`
- `dashboard` → `sections/admin_dashboard.php`

Special tool routes (kept under `admin/` for now)
- `room-config-manager`
- `room-map-manager`
- `area-item-mapper`
- `room-map-editor`

## Legacy Entry Points Removed
All legacy standalone admin entry files under `admin/admin_*.php` have been removed. The canonical source of truth is the router (`/admin/`) loading from `sections/`.

If you have any old bookmarks or links pointing to `admin/admin_<name>.php`, update them to the canonical form:

- `href="/admin/?section=<name>"`

## Link Guidance
- Use route URLs in all templates and scripts:
  - `href="/admin/?section=<name>"`
- Avoid direct links to legacy `admin/admin_<name>.php` (they no longer exist)

## Header/Layout Integration
- Layout and spacing logic are computed in `partials/header.php`.
- Admin content wrapper is `#admin-section-content`.
- Padding variables set via JS:
  - `--wf-admin-total-pad` (header + navbar)
  - `--wf-admin-content-pad` (extra beyond header)

## Migration Status
All sections have been consolidated under `sections/` and are loaded exclusively via the router. Legacy entrypoints were removed as part of the consolidation.

## Test Checklist
- Direct navigation to each route renders correctly via router.
- Header “Settings” link routes to the router (desktop + mobile + dynamic injection).
- No content duplicates are rendered from legacy pages.
- Admin content starts below the header + navbar (no overlap).

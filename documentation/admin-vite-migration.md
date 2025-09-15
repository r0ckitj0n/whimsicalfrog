# Admin Vite Migration and Runtime Guide

This document explains the admin-side Vite migration, where bundles are emitted, how to add new admin pages, and how to verify functionality safely.

## Overview

> Note: All legacy admin entrypoints under `admin/admin_*.php` have been removed. The canonical routing is via `/admin/?section=<name>` and implementations live under `sections/` as `sections/admin_<name>.php`. Any references below to `admin/admin_<name>.php` are historical context only and should be mapped to the canonical router/sections.

- All admin JS/CSS is being migrated to Vite-managed, source-level modules per best practices.
- Admin thin-delegators forward to canonical `sections/*` when available (avoids duplication/conflicts).
- Inline scripts are replaced by Vite modules; inline styles become classes in `src/styles/`.
- Modals follow `WFModalUtils` and `WFModals` conventions for show/hide, `aria-hidden`, and scroll lock.

## Affected Pages and Emits (canonical)

Canonical routes and their section files:
- Customers: `/admin/?section=customers` → `sections/admin_customers.php`
  - Emits: `vite('js/admin-customers.js')`
  - Page data: `#customer-page-data`
- Email Settings: `/admin/?section=settings` → `sections/admin_settings.php`
  - Emits: `vite('js/admin-email-settings.js')`
  - Inline toast script removed
- Account Settings: `/admin/?section=settings` → `sections/admin_settings.php`
  - Emits: `vite('js/admin-account-settings.js')`
  - Page data: `#account-settings-data`
- POS: `/admin/?section=pos` → `sections/admin_pos.php`
  - Emits: `vite('js/admin-pos.js')`
  - Page data: `#pos-data`
- Orders: `/admin/?section=orders` → `sections/admin_orders.php`
  - Emits: `vite('js/admin-orders.js')`
  - Page data: `#order-page-data`
- Cost Breakdown Manager: `/admin/?section=inventory` (tooling) → `sections/admin_inventory.php`
  - Emits: `vite('js/admin-cost-breakdown.js')`
  - Placeholder `<script type="text/plain">` removed

## Vite Entries and Modules

See `vite.config.js` → `build.rollupOptions.input`.

Entries added:
- `js/admin-email-settings.js` → `src/entries/admin-email-settings.js` → `src/js/admin-email-settings.js`
- `js/admin-customers.js` → `src/entries/admin-customers.js` → `src/js/admin-customers.js`
- `js/admin-account-settings.js` → `src/entries/admin-account-settings.js` → `src/js/admin-account-settings.js`
- `js/admin-orders.js` → `src/entries/admin-orders.js` → `src/js/admin-orders.js`
- `js/admin-pos.js` → `src/entries/admin-pos.js` → `src/js/admin-pos.js`
- `js/admin-cost-breakdown.js` → `src/entries/admin-cost-breakdown.js` → `src/js/admin-cost-breakdown-manager.js`

## Styles

- Admin-scoped CSS rules live in `src/styles/admin-settings.css` (overlays, scroll behavior, chips, badges).
- Additional shared styles continue under `src/styles/`.

## Modal Conventions

Follow `documentation/Modal_Conventions_and_Upgrade_Guide.md`:
- Use `.show`/`.hidden` to toggle visibility; always set `aria-hidden` accordingly.
- Use `WFModals.lockScroll()`/`unlockScrollIfNoneOpen()` for scroll control.
- Prefer `WFModalUtils.ensureOnBody(el)` when injecting overlays.

## How to Add a New Admin Page with Vite

1. Create a module:
   - Path: `src/js/admin-<page>.js`
   - Optional: add CSS in `src/styles/` and import it from the entry/module as needed.
2. Create a Vite entry:
   - Path: `src/entries/admin-<page>.js`
   - Content:
     ```js
     import '../js/admin-<page>.js';
     ```
3. Register the entry in `vite.config.js`:
   ```js
   'js/admin-<page>.js': resolve(__dirname, 'src/entries/admin-<page>.js'),
   ```
4. Emit the bundle from the PHP page:
   ```php
   if (function_exists('vite')) {
       echo vite('js/admin-<page>.js');
   }
   ```
5. Replace any inline `<script>` with the new module logic.
6. Move inline styles to `src/styles/` and toggle classes in JS.

## Verification Checklist (canonical routes)

Run through the following for each page after changes.

- **General**
  - Page loads with no PHP errors (check logs) and no console errors
  - Vite bundle is requested/loaded (network tab) and executed (console logs if enabled)

- **Admin Customers (`/admin/?section=customers`)**
  - `vite('js/admin-customers.js')` present in the HTML
  - JSON `#customer-page-data` contains expected keys
  - UI behaviors (view/edit modal, actions) work; no inline script required

- **Admin Email Settings (`/admin/?section=settings`)**
  - Inline toast script is absent
  - Notifications show via `src/js/admin-email-settings.js` when `$message` is present

- **Account Settings (`/admin/?section=settings`)**
  - `vite('js/admin-account-settings.js')` present
  - Submit updates via `/functions/process_account_update.php`; success/error messages surface in the DOM

- **POS (`/admin/?section=pos`)**
  - `vite('js/admin-pos.js')` present
  - `#pos-data` JSON loads; search/cart interactions function

- **Orders (`/admin/?section=orders`)**
  - `vite('js/admin-orders.js')` present
  - Order JSON payload present; inline helpers replaced by module logic
  - Modals and notifications operate via module

- **Cost Breakdown Manager (Inventory tooling)**
  - `vite('js/admin-cost-breakdown.js')` present
  - Placeholder `<script type="text/plain">` removed
  - Interactions (load item, add lines, update totals) run via module

- **Delegators**
  - Legacy delegators have been removed. All routes resolve via `/admin/?section=<name>` to `sections/admin_<name>.php`.

## Notes / Rollback

- To disable a bundle temporarily, comment its `echo vite('...')` line in the PHP page.
- To revert a migration quickly, you can re-enable an inline script (if preserved in history) while keeping the Vite path for later.
- Use `php -l` to quickly confirm syntax across touched files.

## Maintenance Tips

- Keep future admin behaviors in `src/js/admin-*.js` and register new entries in `vite.config.js`.
- Ensure modal behaviors follow the conventions guide for consistency and accessibility.
- Prefer server responses that include clear `{ success, message }` to simplify UI notifications.

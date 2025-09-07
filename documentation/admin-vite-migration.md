# Admin Vite Migration and Runtime Guide

This document explains the admin-side Vite migration, where bundles are emitted, how to add new admin pages, and how to verify functionality safely.

## Overview

- All admin JS/CSS is being migrated to Vite-managed, source-level modules per best practices.
- Admin thin-delegators forward to canonical `sections/*` when available (avoids duplication/conflicts).
- Inline scripts are replaced by Vite modules; inline styles become classes in `src/styles/`.
- Modals follow `WFModalUtils` and `WFModals` conventions for show/hide, `aria-hidden`, and scroll lock.

## Affected Pages and Emits

- `admin/admin_marketing.php` → delegates to `sections/admin_marketing.php`
- `admin/admin_reports.php` → delegates to `sections/admin_reports.php`
- `admin/admin_settings.php` → thin delegator to `sections/admin_settings.php` (pruned)
- `admin/admin_customers.php`
  - Emits: `vite('js/admin-customers.js')`
  - Page data: `#customer-page-data`
- `admin/admin_email_settings.php`
  - Emits: `vite('js/admin-email-settings.js')`
  - Inline toast script removed
- `admin/account_settings.php`
  - Emits: `vite('js/admin-account-settings.js')`
  - Page data: `#account-settings-data`
- `admin/admin_pos.php`
  - Emits: `vite('js/admin-pos.js')`
  - Page data: `#pos-data`
- `admin/admin_orders.php`
  - Emits: `vite('js/admin-orders.js')`
  - Page data: `#order-page-data`
- `admin/cost_breakdown_manager.php`
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

## Verification Checklist

Run through the following for each page after changes.

- **General**
  - Page loads with no PHP errors (check logs) and no console errors
  - Vite bundle is requested/loaded (network tab) and executed (console logs if enabled)

- **Admin Customers (`admin/admin_customers.php`)**
  - `vite('js/admin-customers.js')` present in the HTML
  - JSON `#customer-page-data` contains expected keys
  - UI behaviors (view/edit modal, actions) work; no inline script required

- **Admin Email Settings (`admin/admin_email_settings.php`)**
  - Inline toast script is absent
  - Notifications show via `src/js/admin-email-settings.js` when `$message` is present

- **Account Settings (`admin/account_settings.php`)**
  - `vite('js/admin-account-settings.js')` present
  - Submit updates via `/functions/process_account_update.php`; success/error messages surface in the DOM

- **POS (`admin/admin_pos.php`)**
  - `vite('js/admin-pos.js')` present
  - `#pos-data` JSON loads; search/cart interactions function

- **Orders (`admin/admin_orders.php`)**
  - `vite('js/admin-orders.js')` present
  - Order JSON payload present; inline helpers replaced by module logic
  - Modals and notifications operate via module

- **Cost Breakdown Manager (`admin/cost_breakdown_manager.php`)**
  - `vite('js/admin-cost-breakdown.js')` present
  - Placeholder `<script type="text/plain">` removed
  - Interactions (load item, add lines, update totals) run via module

- **Delegators**
  - `admin/admin_marketing.php`, `admin/admin_reports.php`, `admin/admin_settings.php` are thin delegators
  - `admin/admin_settings.php` includes `sections/admin_settings.php` and returns (verified lint OK)

## Notes / Rollback

- To disable a bundle temporarily, comment its `echo vite('...')` line in the PHP page.
- To revert a migration quickly, you can re-enable an inline script (if preserved in history) while keeping the Vite path for later.
- Use `php -l` to quickly confirm syntax across touched files.

## Maintenance Tips

- Keep future admin behaviors in `src/js/admin-*.js` and register new entries in `vite.config.js`.
- Ensure modal behaviors follow the conventions guide for consistency and accessibility.
- Prefer server responses that include clear `{ success, message }` to simplify UI notifications.

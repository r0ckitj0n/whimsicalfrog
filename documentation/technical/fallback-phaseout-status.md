# Fallback Phase-out: Status and Plan

Last updated: Oct 16, 2025

## Completed
- **Account Settings Modal (global, non-iframe)**
  - Files: `src/js/account-settings-modal.js`, `sections/admin_settings.php`, `src/entries/app.js`
  - Ensured global import via Vite entry. Removed iframe usage for admin button; standardized opener behavior.
- **Users/Addresses API normalization**
  - Files: `api/users.php`, `api/customer_addresses.php`
  - Accept/return camelCase alongside snake_case; modal JS prefers camelCase with fallback. Light `[WF]` diagnostics on load.
- **Strict user ID source**
  - Files: `partials/header.php`, `src/js/account-settings-modal.js`
  - Prefer `data-user-id` attributes; removed `sessionStorage` fallback.
- **Core timing fix**
  - File: `whimsical-frog-core-unified.js`
  - Emit `core:ready` after `window.wf` alias exists. Eliminated `wf.addModule is not a function` race.
- **Room modal background/letterboxing fix**
  - Files: `src/styles/components/room-modal.css`, `src/styles/components/room-iframe.css`, `src/modules/room-modal-manager.js`
  - Flex/grid layout, exact aspect on body, ensure inner wrappers fill height. Apply runtime background class to both `.room-modal-container` and wrapper for redundancy.
- **Cart modal scrollbar stabilization (CSS-only)**
  - Files: `src/styles/cart-modal.css`, `src/js/cart-modal.js`
  - Use grid rows `auto minmax(0,1fr) auto` inside modal; middle row `#cartModalItems` is the scroll host with `overflow-y:auto`, `min-height:0`, `touch-action:pan-y`, `-webkit-overflow-scrolling:touch`. Container uses `90vh/90dvh` with fallback.
  - Upsells attach into the scroll host and remain hidden until near-bottom (`maybeRenderUpsells`).

- **Admin Settings: centralized modal openers; fallbacks disabled**
  - Files: `src/modules/admin-settings-lightweight.js`, `sections/admin_settings.php`
  - Centralized openers for Categories, Attributes, Customer Messages, Shopping Cart, Size/Color Redesign, Deploy Manager, DB Schema Audit, Repo Cleanup, Dashboard Config, AI Tools, AI Settings, Square Settings, Background Manager, CSS Catalog, Room Map Editor, Area-Item Mapper, Template Manager.
  - Disabled legacy inline fallback IIFEs in `sections/admin_settings.php` via early returns.

- **Orders page: inline guard/fallback removed**
  - File: `sections/admin_orders.php`
  - Removed inline module guard and robust fallback; now exclusively uses `src/js/admin-orders.js`. Receipt modal and delete-confirm modals remain, controlled by the module.

- **Payment modal address reads normalized**
  - File: `src/js/payment-modal.js`
  - Reads camelCase fields (`addressName`, `addressLine1`, `addressLine2`, `zipCode`, `isDefault`); write payloads remain snake_case. Fixed fallback zip to `business_postal`.

## In Progress / Verification
- **Cart modal**
  - Verify scrollbar on desktop/mobile with many items. If missing, collect computed sizes:
    - `#cartModalOverlay .confirmation-modal > div` height (px)
    - `.cart-header` height (px)
    - `#cartModalItems` clientHeight vs scrollHeight
    - `.cart-modal-footer` height (px)
- **Addresses normalization follow-through**
  - Remove remaining front-end defensive fallbacks after API stability confirmed.
- **`data-user-id` enforcement**
  - Ensure presence across all logged-in pages.
- **Modal openers consolidation**
  - Prefer global delegated openers; remove legacy inline openers after parity checks.
- **Diagnostics/flags cleanup**
  - Remove transitional console diagnostics once modules are verified.

- **Repo-wide verification of fallbacks**
  - Scanned `sections/` and `components/` for inline fallback patterns; no remaining global fallback openers detected after Admin Settings/Orders cleanup. Tool pages under `sections/tools/` maintain local-in-iframe interactions only.

## Next Up
- Harden mobile behavior further if needed (iOS inertial scrolling and body lock interplay) with minimal, source-level CSS.
- Continue CSS/JS consolidation under Vite:
  - Audit scattered styles, remove overrides, and migrate remaining legacy includes to `src/styles/**` and ES modules.

## Notes
- We avoid JS inline style writes to comply with repo standards (see `scripts/disallow-inline-styles.mjs`).
- All modal/system CSS and JS are managed via Vite entry points and component files.

## Guardrails & Policies
- **No inline fallbacks**
  - Do not add delegated inline `<script>` handlers for modal open/openers in PHP templates. All modal behavior must live in Vite modules under `src/js/**` or `src/modules/**`.
- **Use semantic classes for styling**
  - Styling must target semantic classes, not `[data-action]`. Keep `data-action` strictly for JS behavior.
- **Overlay API contract**
  - Overlays use `.admin-modal-overlay` with `.show` class for visibility. Use `__wfShowModal(id)`/`__wfHideModal(id)` or the module’s equivalent utilities.
- **CI guard: no inline fallbacks**
  - New guard `scripts/guards/no-inline-fallbacks.mjs` scans for suspicious inline fallback patterns in `sections/` (excluding `sections/tools/`). CI fails if found. Integrate into lint workflow.
- **Source-only CSS/JS under Vite**
  - Add new behavior/styles via Vite-managed sources and entries. Avoid overrides; change source where the component lives.

This my chat history when I started removing all of the fallbacks. Please continue with a concerted effort to remove fallbacks:
I will add a static top-level import of the Account Settings modal module in the actual Vite entry used by the site (
src/entries/app.js
), so the handlers are always installed and you don't rely on dynamic imports. This should make the modal load reliably without needing a network fetch for that module. Now applying the change.

app.js
+1
account-settings-modal.js
+28
account-settings-modal.js
+16
Searched
pointerdown in sections/admin_settings.php (0)
admin_settings.php
+24
-1
Findings
Module wasn’t loading on click: The dynamic import of 
account-settings-modal.js
 wasn’t firing, so no handlers installed. No network request showed because the module never got requested.
Competing handlers: Admin Settings has multiple delegated listeners. Some stopPropagation and can preempt later handlers, preventing the modal opener from running when it did load.
Markup + iframe variant: On Admin Settings, the modal is an iframe (#accountSettingsFrame) and must be primed with data-src when opened.
Changes Made
Always load the modal module
Added a top‑level static import in 
src/entries/app.js
:
import "../js/account-settings-modal.js";
Removed the previous dynamic import in that entry.
Make the opener preempt other handlers
In 
src/js/account-settings-modal.js
:
Added capture-phase click, and even earlier capture-phase pointerdown, to open the modal via 
openAndLoad()
 before other handlers block it.
Reparent overlay to document.body, add over-header on admin, remove inline z-index.
Prime iframe #accountSettingsFrame src from data-src on open.
Broaden user-id lookup: body[data-user-id] → any [data-user-id] → sessionStorage.user.
Create modal from template if not already present.
Robust inline fallback on the Admin Settings page
In 
sections/admin_settings.php
:
Replaced the “let global handler open” stub. Now, clicks on [data-action="open-account-settings"] directly open #accountSettingsModal, reparent to body, add over-header, and set the iframe src from data-src.
This ensures the modal opens even if the global module fails or loads late.
How to Verify
Hard reload the page to ensure the new entry bundle is active.
On the Admin Settings page:
Click the “Account Settings” button (#accountSettingsBtn, data-action="open-account-settings").
Expected:
#accountSettingsModal overlay becomes visible.
#accountSettingsFrame loads /sections/admin_router.php?section=account-settings&modal=1 (visible in Network tab).
From the site header:
Click the username or any header link with data-action="open-account-settings".
Expected: The same overlay opens; if on admin routes, overlay shows above the admin nav.
Notes on Fallback Removal
We kept strict data validation in 
src/js/account-settings-modal.js
 but added safe user-id sourcing (DOM or session) to avoid blocking the UI. The modal opens even if user data fails fetch; an error message appears in #accountSettingsError.
Status
Implemented static module load, capture-phase openers, iframe priming, and an inline fallback on the admin page.
Please hard reload and re-test both Admin Settings and header triggers. If it still fails, share any [AccountSettings] console logs so I can pinpoint the next blocker.
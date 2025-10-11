> Note: Technical Reference — Historical/Deep Technical context. For current behavior and routes, see documentation/ADMIN_GUIDE.md.

# Modal Conventions and Upgrade Guide

This document captures the standardized patterns used across all modals in the WhimsicalFrog codebase. Follow these conventions for any new or migrated modals to ensure consistent behavior, accessibility, and styling.

## Goals

- Consistent show/hide behavior across all modals
- Reliable scroll locking/unlocking when modals are open
- Accessibility and keyboard support (Escape, focus management)
- Centralized, Vite-managed JS and CSS
- Shared backend bootstrap for API endpoints

## Shared Utilities

File: `src/core/modalUtils.js`

- `ensureOnBody(el)`
  - Appends the overlay/modal element to `document.body` to avoid clipping/transform/overflow issues.
- `showModalById(id)` / `hideModalById(id)`
  - Adds/removes `.show`, sets `aria-hidden`, and uses `WFModals.lockScroll()` / `WFModals.unlockScrollIfNoneOpen()`.
- `forceVisibleStyles(el)`
  - Defensive styles for legacy overlays if necessary.

Also available globally via `window.WFModalUtils.*` for use without imports in legacy code paths.

## Show / Hide Mechanics

- On show:
  - `ensureOnBody(overlay)`
  - Add `.show`
  - Set `aria-hidden="false"`
  - Lock scroll via `WFModals.lockScroll()`
- On hide:
  - Remove `.show`
  - Set `aria-hidden="true"`
  - Unlock scroll via `WFModals.unlockScrollIfNoneOpen()`
- If a particular overlay family uses `.hidden` for visibility (e.g., `modal-overlay hidden`):
  - Keep removing/adding `.hidden` as primary toggle, `.show` is harmless but optional.

## Accessibility

- Add `role="dialog"` and `aria-modal="true"` to containers representing the dialog.
- Manage `aria-hidden` on the overlay/container.
- Provide Escape-to-close and backdrop click to close where appropriate.
- Focus the first interactive control or the close button on open.

## Scroll Locking

- Use `WFModals.lockScroll()` when a modal opens.
- Use `WFModals.unlockScrollIfNoneOpen()` when a modal closes.
- The helper considers multiple modal types and only unlocks when none are open.

## Overlay Placement

- Always attach overlays to `document.body` using `ensureOnBody()`.
- This avoids stacking context issues and clipping from parent containers with transforms/overflow.

## API Endpoint Conventions

- Use shared configuration at the top of modal-related endpoints:
  - `require_once __DIR__ . '/config.php';`
  - `require_once __DIR__ . '/../includes/response.php';` (for JSON-based endpoints)
  - `require_once __DIR__ . '/../includes/auth_helper.php';` when admin-gated
- Return consistent JSON for programmatic consumers using `Response::success` / `Response::error`.
- For endpoints returning HTML snippets (e.g., detailed item modal), never return an empty body; on error, return an HTML comment to avoid breaking client rendering.

## CSS Guidelines

- Keep all modal styles in Vite-managed CSS:
  - `src/styles/components/modal.css`
  - `css/global-modals.css` (legacy; migrate incrementally)
- Visibility mechanics:
  - `confirmation-modal-overlay` uses `.show` to become visible.
  - `modal-overlay`/`admin-modal-overlay` typically use `.hidden` to hide. Keep that behavior, or add `.show` if you standardize the family.
- Ensure overlays are fixed, full-screen, and have a high z-index.

## Migration Checklist (per modal)

1. Replace ad-hoc show/hide with:
   - Ensure on body, add/remove `.show`, set `aria-hidden`
   - Use `WFModals.lockScroll()` / `WFModals.unlockScrollIfNoneOpen()`
2. Verify ESC/backdrop click close behaviors.
3. Ensure the markup has `role="dialog"` and `aria-modal="true"` if applicable.
4. Ensure the overlay is positioned as fixed full-screen and above content.
5. Remove inline script behavior; move logic to Vite module.
6. For API usage:
   - Use shared bootstraps and return JSON on programmatic endpoints.
   - For HTML endpoints, never return an empty body.

## Files Updated in Current Upgrade

- Utilities
  - `src/core/modalUtils.js` (new)
- Auth/Commerce Modals
  - `src/js/login-modal.js`
  - `src/js/cart-modal.js`
  - `src/js/checkout-modal.js`
  - `src/js/payment-modal.js`
  - `src/js/receipt-modal.js`
- Item Modals
  - `src/js/detailed-item-modal.js`
  - `api/render_detailed_modal.php` (bootstrapped to shared config)
  - `components/detailed_item_modal.php` (markup already Vite-friendly)
- AI Modal
  - `src/modules/ai-processing-modal.js`

## Notes

- Some overlay families still primarily use `.hidden` for visibility. The code changes tolerate this by both removing `.hidden` and adding `.show` (no conflict).
- If desired, we can fully standardize all overlays to use `.show` to become visible and remove `.hidden` usage; this requires minor CSS updates.

## Planned Next Steps

- Sweep admin-specific overlays in `admin/` and `components/` for the same treatment.
- Verify all admin modal endpoints include shared config/auth/response bootstrap and return consistent JSON.
- Consolidate legacy CSS in `css/global-modals.css` into `src/styles/components/modal.css` over time.

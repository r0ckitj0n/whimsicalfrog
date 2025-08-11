# WhimsicalFrog JavaScript Architecture Audit

_Last updated: 2025-07-18_

> This document is a living audit of all JavaScript contained in `js/`.  
> The goal is to identify each file's current responsibility, major exports/globals, key dependencies, and a deprecation/refactor plan.
>
> **Legend**  
> • ✅ – actively used & OK  
> • ⚠️ – works but contains legacy patterns / needs refactor  
> • 🗑️ – unused / duplicate – candidate for removal

| File | Status | Primary Responsibility | Key Globals / Exports | Direct Dependencies |
|------|--------|------------------------|-----------------------|----------------------|
| `whimsical-frog-core.js` | ✅ | Lightweight core framework (module registry, event bus, logging) | `WhimsicalFrog` (namespace) | none |
| `utils.js` | ⚠️ | Assorted helpers (DOM, string, etc.) – should be split into focused libs | many top-level fns | none |
| `central-functions.js` | ⚠️ | Historic catch-all; still provides image error handler, sale price checker, etc. | multiple globals (`setupImageErrorHandling`, `checkAndDisplaySalePrice`, …) | `whimsical-frog-core.js` |
| `wf-unified.js` | ⚠️ | Bridges legacy global functions to the Core module system; ensures compatibility inside bundle or standalone scripts. | `window.WF_UNIFIED_READY` flag + shims | `whimsical-frog-core.js`, `central-functions.js` |
| `global-popup.js` | ✅ | UnifiedPopupSystem class, registers global popup helpers | `window.showGlobalPopup`, etc. | `whimsical-frog-core.js` (optional) |
| `room-modal-manager.js` | ✅ | Handles room modal overlay, preload, iframe injection | depends on `WhimsicalFrog.ready` | `whimsical-frog-core.js` |
| `global-item-modal.js` | ✅ | Large item detail modal | popup & cart helpers | core + popup |
| `cart-system.js` (`js/modules/`) | ⚠️ | Cart state & UI; mixes logic/UI; ES-module refactor recommended | `window.cart`, many fns | core, central-functions |
| `main-application.js` | ✅ | High-level page tasks (cart counter, login handling, background) | registered module `MainApplication` | core, cart |
| `main.js` | 🗑️ | Obsolete page-level legacy code—**removed from debug list & bundle**  | many duplicate fns | _was overriding newer code_ |
| `search.js`, `analytics.js`, `sales-checker.js` | ⚠️ | Single-feature scripts; style varies | various | utils, core |
| `room-*.js` utilities | ✅ | Coordinate/css/ event helpers for room pages | globals | utils |
| [...all remaining files listed in `js/`] | (audit continues) | | | |

## To-Do Roadmap

1. **Phase-0** (now) – Stop loading `main.js` everywhere (done).  
2. **Phase-1** – Move remaining global helpers from `central-functions.js` into modules.  
3. **Phase-2** – Introduce ES-module build (Rollup/Vite). Keep `scripts/bundle-js.php` only as fallback.  
4. **Phase-3** – Delete / archive files flagged 🗑️ after verification.

Feel free to edit this document as we progress.

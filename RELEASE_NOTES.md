# Release Notes - 2025-10-10

## Summary
- Renamed `src/js/global-item-modal.js` to `src/js/detailed-item-modal.js` as the canonical detailed item modal.
- Updated all runtime imports and comments to the new name.
- Removed obsolete patch scripts referencing the old filename.
- Updated technical documentation references accordingly.

## Key Changes
- Code:
  - Canonical module: `src/js/detailed-item-modal.js` (full implementation).
  - Updated references in `src/entries/pos.js`, `src/js/app.js`, `src/room/event-manager.js`, `src/ui/global-popup.js`, `src/core/action-registry.js`.
  - Deleted legacy module `src/js/global-item-modal.js`.
- Docs:
  - `documentation/technical/MODAL_CONVENTIONS_AND_UPGRADE_GUIDE.md` now lists `src/js/detailed-item-modal.js` under Item Modals.
- Tooling:
  - Removed `patch-global-item-modal.sh` and `scripts/dev/patch-global-item-modal.sh`.

## Rationale
- Standardize naming around “detailed-item-modal” and remove confusion with legacy file name.
- Eliminate unused scripts tied to the old module.

## Next
- None. Optional: verify modals on Shop, Rooms, and POS open correctly post-rename.

# Release Notes - 2025-09-06

## Summary
- Consolidated all project documentation into the canonical `documentation/` folder.
- Updated scripts/tooling to reference `documentation/`.
- Excluded `documentation/` from live deployments.

## Key Changes
- Documentation move:
  - Moved `docs/` tree to `documentation/` via `git mv` to preserve history.
  - Updated internal modal-related documentation links from `/docs/...` → `/documentation/...`.
- Tooling updates:
  - `scripts/css-inventory.mjs` now writes to `documentation/frontend/CSS_REORG_PLAN.md` and console message updated.
  - `scripts/disallow-inline-styles.mjs` guard now ignores `documentation/legacy-duplicates/`.
  - `.lintstagedignore` updated to ignore `documentation/**`.
- Deployment:
  - `scripts/deploy.sh` and `scripts/deploy_full.sh` exclude `documentation/` from SFTP mirror so docs never sync to live.

## Rationale
- Single source of truth for docs improves discoverability and prevents drift.
- Excluding docs from deploys prevents publishing internal notes and reduces upload size.

## Next
- Optionally add CODEOWNERS/labels for `documentation/`.
- Update any external automation that referenced `docs/` to use `documentation/`.

# Release Notes - 2025-09-02

## Summary
- Fix Vite dev proxy CSS MIME mismatch and stabilize HMR.
- Ensure Vite dev server binds to 127.0.0.1 for consistent WS connectivity.
- Improve proxy reliability (timeouts, connection handling, 127.0.0.1 fallback).

## Key Changes
- `vite-proxy.php`
  - Treat `/src/**/*.css` as module imports; append `?import` upstream.
  - Serve module-style CSS as `application/javascript`; plain CSS remains `text/css`.
  - Use short timeouts and `Connection: close` to avoid hanging sockets.
  - Prefer `127.0.0.1` over `localhost` to avoid IPv6 issues.
- `vite.config.js`
  - `server.host` and `server.hmr.host` set to `127.0.0.1`.

## Dev Notes
- Verified via curl: module CSS now returns `Content-Type: application/javascript`.
- HMR connects reliably (`[vite] connected`).
- Repository history scrubbed to remove a detected Anthropic API key; forced update to `main`.

## Next
- Prefer PR workflow for future changes (no direct pushes to `main`).
- Run end-to-end checks for room modals and login.

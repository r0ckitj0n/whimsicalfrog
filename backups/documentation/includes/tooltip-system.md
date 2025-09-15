# Admin Tooltip System

This document describes how admin tooltips work in WhimsicalFrog. Tooltip CONTENT is dynamic (from the database). Tooltip styling and loader logic are Vite-managed static assets.

## Runtime Components

- `src/modules/tooltip-manager.js`
  - Fetches JSON from `api/help_tooltips.php?action=get&page_context=<context>`
  - Attachment order per tooltip record:
    1. `#<element_id>`
    2. `[data-help-id="<element_id>"]`
    3. `[data-action="<element_id>"]` (fallback)
  - Auto-initialized on admin routes by `src/js/app.js`.
- `src/styles/components/tooltip.css`
  - Styles the tooltip box, arrow, and z-index.
  - Imported by `src/styles/main.css`.

## API Endpoints

- `GET /api/help_tooltips.php?action=get&page_context=settings`
  - Public read access to fetch tooltips for a page context.
- `GET /api/help_tooltips.php?action=list_all&admin_token=...`
  - Admin-only read. Used by tools to inventory existing entries.
- `POST /api/help_tooltips.php?action=upsert`
  - Admin-only write. Insert-or-update by unique `element_id`.
  - Body (JSON):
    ```json
    {
      "admin_token": "...",
      "element_id": "dashboardConfigBtn",
      "page_context": "settings",
      "title": "Dashboard Configuration",
      "content": "Customize your control center...",
      "position": "bottom",
      "is_active": 1
    }
    ```

## Page Contexts

Typical contexts include: `settings`, `inventory`, `orders`, `customers`, `reports`, `marketing`, `pos`, `db-status`, `db-web-manager`, `room-config-manager`, `cost-breakdown-manager`, and `common` (for admin nav tabs used everywhere).

## Authoring Tooltips – Preferred Selectors

- Prefer a stable DOM id (`id="..."`) when available.
- Otherwise add a semantic `data-help-id="..."` in templates to target precisely.
- As a fallback, `TooltipManager` can bind by the element’s `data-action` name if the `element_id` matches the action string.

## Seeding, Auditing, Exporting

NPM scripts are defined in `package.json`.

- Export all tooltips to a timestamped JSON:
  ```bash
  npm run tooltips:export
  ```
  Outputs to `scripts/data/help-tooltips-<timestamp>.json`.

- Audit for duplicates & repeated content (read-only):
  ```bash
  npm run tooltips:audit
  ```

- Seed curated, snarky tooltips:
  ```bash
  # Preview (no DB writes)
  npm run tooltips:seed:dry

  # Apply (uses upsert; idempotent)
  npm run tooltips:seed
  ```

Scripts used:
- `scripts/dev/export-admin-tooltips.mjs`
- `scripts/dev/audit-tooltips.mjs`
- `scripts/dev/seed-admin-tooltips.mjs` (uses `action=upsert`)

## Adding New Tooltips

1. Choose an `element_id` and ensure there is a stable binding target:
   - Add `id="elementId"` or `data-help-id="elementId"` to the element, or make sure `data-action="elementId"` exists.
2. Create or upsert via API (or add to the seeding script if it’s a common control):
   - `POST /api/help_tooltips.php?action=upsert` (admin token required)
3. Reload the admin page and hover to verify.

## Notes

- Global toggle: run `window.toggleGlobalTooltips()` in DevTools to enable/disable tooltips (persisted via `localStorage` key `wf_tooltips_enabled`).
- Styling/z-index: uses `--z-index-tooltip` defined in `src/styles/z-index.css`.
- Content policy: keep snarky but helpful. Keep it short and scannable.

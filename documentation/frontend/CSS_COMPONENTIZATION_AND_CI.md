# CSS Componentization and CI Guardrails

This document describes how we organize component CSS, enforce no-inline-styles, and run automated checks in CI.

## Component CSS Structure

- Location: `src/styles/components/`
- Naming: one file per UI surface/module, e.g. `popup.css`, `room-modal.css`, `search-modal.css`.
- Import in `src/styles/main.css` only. Avoid ad-hoc imports in JS; styles are Vite-managed centrally.

Example import block in `src/styles/main.css`:

```css
@import url('./components/popup.css');
@import url('./components/room-modal.css');
@import url('./components/search-modal.css');
```

## No Inline Styles Policy

- Inline styles are disallowed in templates and in JS-generated markup.
- Guard script: `scripts/disallow-inline-styles.mjs` scans staged files (AST-based) and fails on `style="..."` attributes inside string/template literals.
- Templated HTML must use CSS classes only. Create or extend a component stylesheet if a new rule is needed.

### PHP helper deprecation

The `getImageTag()` helper in `includes/functions.php` has a deprecated `$style` parameter which is now ignored to comply with this policy:

```php
function getImageTag($imagePath, $altText = '', $class = '', $style = '')
```

If you need styling, pass a semantic class via `$class` and define rules in a component CSS file.

## Linting

- ESLint: `npm run lint`
- Stylelint: `npm run lint:css`
- Inline-style guard: `npm run guard:styles`
- Orphaned CSS checker: `npm run check:css:orphans`

These run locally via pre-commit hooks and in CI on pushes/PRs.

## CI Workflow

GitHub Actions workflow at `.github/workflows/ci.yml` runs:

```bash
npm ci || npm install
npm run guard:styles
npm run lint
npm run lint:css
npm run check:css:orphans
```

This ensures no inline styles are introduced and code style is enforced.

## Orphaned CSS Checker

- Script: `scripts/check-orphaned-css.mjs`
- Purpose: Detect CSS files in `src/styles/` that are not referenced by any JS/TS import or by `@import` chains starting from `src/styles/main.css`.
- Run locally: `npm run check:css:orphans`
- Auto-archive: `npm run check:css:orphans:write` will move reported orphans to `backups/unused_styles/` preserving their relative path from `src/styles/`. If a filename collision occurs, a timestamp suffix is added.
- If any orphans are reported, either:
  - Import them from `src/styles/main.css` if they are needed, or
  - Archive them to `backups/unused_styles/` (preferred over deletion).

Note: CI runs the non-write variant only to prevent destructive moves in pull requests.

## Migration Playbook

1. Identify legacy CSS tightly coupled to a UI surface.
2. Move rules into a new or existing file under `src/styles/components/`.
3. Replace attribute-based selectors with semantic classes.
4. Import the component stylesheet from `src/styles/main.css`.
5. Remove or archive the legacy stylesheet to `backups/unused_styles/` once unused.
6. Verify visual parity in the browser, then run:
   - `npm run guard:styles && npm run lint && npm run lint:css && npm run build`

## Recent Changes

- `src/styles/components/search-modal.css` added. `src/styles/main.css` updated to import it.
- Comment in `src/modules/search-system.js` now references the component path.
- `getImageTag()` `$style` parameter deprecated and ignored.
- CI workflow added to run guard and linters on PRs.
- Legacy `src/styles/search-modal.css` archived to `backups/unused_styles/`.

## Z-Index Tokens and Policy

This project uses a single source of truth for stacking contexts. All `z-index` values must reference tokens from `src/styles/z-index.css`. Do not use numeric literals in component styles.

### Token Table (full)

Defined in `src/styles/z-index.css` under `:root`:

```css
/* Core site chrome */
--z-index-page-header: 3000;
--z-index-site-nav: 2950;

/* Modals / overlays */
--z-index-room-modal: 2400;
--z-index-room-modal-header: 2450;
--z-index-cart-overlay: 10070;
--z-index-checkout-overlay: 10060;
--z-index-checkout-content: 10061;
--z-index-receipt-overlay: 10080;
--z-index-global-popup: 100200; /* emergency/highest overlay tier */

/* UI chrome */
--z-index-loader: 10500;
--z-index-toast: 11000;          /* wf notifications container */
--z-index-dropdown: 11500;
--z-index-tooltip: 12000;

/* Component-specific low layers */
--z-index-checkout-badge: 3;     /* inline badges inside cards */

/* Internal modal parts */
--z-index-modal-content: 420;    /* loaders/content inside modals */
--z-index-modal-front: 430;

/* Legacy/compat and helpers (subset) */
--z-overlay: 10050;              /* generic overlay backdrop */
--z-overlay-content: 10051;
--z-admin-overlay: 10100;        /* admin-special overlays */
--z-admin-overlay-content: 10101;
--z-index-header-ink: 200;       /* header text and links */
--z-index-nav: 300;              /* navigation links/items */
--z-index-global-notification: 500;
--z-inline-popup: 1000;          /* product hover popups */
--z-inline-popup-high: 1100;
--z-badge: 10;                   /* decorative badges */
--z-badge-high: 15;              /* badge hover */
--z-header-over-room-modal: 3000;/* keep header over room modal */
```

Notes:
- Values emphasize relative ordering. Prefer updating tokens rather than sprinkling numbers in components.
- For new layers, add a token here with a clear comment. Keep gaps between tiers to avoid tight coupling.

### Usage Guidelines

- Always reference tokens: `z-index: var(--z-index-tooltip);`
- If a component needs a new layer, propose a token name and add it to `z-index.css` with rationale.
- Avoid overusing high tiers. Most overlays should sit in the 10k–11k range; only critical debug/emergency UI should use the 100k+ tier.

### Enforcement

- Stylelint rule prevents raw numeric `z-index` values outside `z-index.css`:

```json
"declaration-property-value-disallowed-list": {
  "z-index": ["/^\\d+$/"]
}
```

This allows `z-index: var(--...)` and disallows `z-index: 9999` in component styles.

### Examples

```css
/* Correct */
.room-modal-overlay { z-index: var(--z-index-room-modal); }
.confirmation-modal-overlay.checkout-overlay { z-index: var(--z-index-checkout-overlay); }
#wf-notification-container { z-index: var(--z-index-toast); }

/* Avoid */
.some-component { z-index: 9000; /* ❌ use a token */ }
```

## New Utility Classes (2025-09)

Use these classes instead of inline styles:

- `wf-admin-embed-frame`
  - Location: `src/styles/admin-settings.css`
  - Purpose: Standard styling for iframes embedded in admin Settings modals.
  - Applies width 100%, height 70vh, subtle border and radius.

- `wf-field-updating`, `wf-field-success`, `wf-field-error`
  - Location: `src/styles/admin-dashboard.css`
  - Purpose: Background-color status cues for inline field updates in dashboard widgets.
  - Attach to inputs/selects with class `.order-field-update` while saving.

- `rme-canvas-wrap`, `rme-canvas`, `rme-svg`
  - Location: `src/styles/admin-room-map-editor.css`
  - Purpose: Room Map Editor layout and SVG interaction styles (no inline styles).
  - Background image is applied via a data attribute (`data-bg-url`) and an injected scoped CSS rule.

### No Inline Styles – Practical Notes

- JavaScript-generated markup must not include `style="..."` attributes.
- Use semantic classes and add rules in a module stylesheet imported by Vite.
- Where a dynamic background is needed, prefer:
  - A stable class for all static properties (size, repeat, positioning), and

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

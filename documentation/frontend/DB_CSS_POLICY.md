# Database-managed CSS Policy

This project uses Vite to own all CSS structure and rules. The database is only permitted to store a minimal set of theme variables (e.g., brand colors). No server-generated CSS files are allowed.

- Vite bundles all styles under `src/styles/`.
- Admin-tweakable values are injected as CSS variables during render in `partials/header.php`. The injection:
  - Prefers `css_variables` table (if present), falling back to `website_config` rows with `setting_type = 'color'`.
  - Emits a small `<style data-source="db-theme-variables">:root { ... }</style>` block with validated names and values.
- Deprecated endpoints under `api/_deprecated_css_endpoints/` are hard-disabled with HTTP 410.
- `api/website_config.php` no longer permits `custom_css` and returns 410 for legacy CSS variable/output actions. Allowed setting types exclude `css`.

## Rationale
- Ensures performance, cacheability, and consistency by centralizing CSS in the Vite pipeline.
- Preserves limited admin theming via variables without rule-level injection.

## Migration Notes
- If any feature still depends on DB-driven CSS rules, migrate to static classes and reference variables where appropriate.
- CI guards prevent legacy `<link href="*.css">` and inline style usage in live templates.

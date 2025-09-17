# Image Naming Policy and Migration Guide

This document defines the canonical image naming policy for the WhimsicalFrog repo, the guard/audit tooling that enforces it, and the rewrite shims that preserve backward compatibility for legacy file names.

## Policy

- Backgrounds, signs, and logos
  - Lowercase only.
  - Dash-separated words; no underscores, no spaces.
  - Backgrounds MUST start with the prefix `background-` (e.g., `background-room1.webp`).
  - Allowed characters: `[a-z0-9-]` plus the extension.
- Item images (SKUs) under `images/items/`
  - Uppercase allowed to match SKU conventions (e.g., `WF-AR-001A.webp`).
  - Dash-separated preferred; no underscores, no spaces.
- Pairing requirement (guarded folders)
  - In `images/backgrounds/`, `images/items/`, `images/signs/`, and `images/logos/`, every image must have both `.webp` and `.png` variants.
- Global constraints (images/ and src/assets/)
  - No spaces in filenames anywhere.
  - No underscores in image filenames (repo-wide).

## Guard and Audit Tooling

- Guard (hard-fail on violations):
  - `npm run guard:images`
  - Enforces: no spaces; no underscores; lowercase outside `images/items/`; `background-` prefix; `.webp`/`.png` pairing in guarded folders.
- Static code audit:
  - `npm run audit:images:code`
  - Scans PHP/JS/CSS/HTML for image references, verifies existence, and checks `.webp`/`.png` pair presence for references into guarded folders.
- DB/API audit (optional, read-only):
  - `npm run audit:images:db`
  - Uses APIs to enumerate item images and verifies disk + HTTP availability.

### Pre-commit and CI Integration

- Pre-commit: `.husky/pre-commit` runs `guard:images` and `audit:images:code` before `lint-staged`.
- CI: `.github/workflows/ci.yml` runs the image guard and static audit on every push/PR.

## Migration Summary (underscores → dashes)

- Backgrounds (`images/backgrounds/`)
  - `background_home.*` → `background-home.*`
  - `background_room{1..5}.*` → `background-room{1..5}.*`
  - `background_room_main.*` → `background-room-main.*`
  - `background_settings.*` → `background-settings.*`
- Signs (`images/signs/`)
  - `sign_door_roomN.*` → `sign-door-roomN.*`
  - `sign_main.*` → `sign-main.*`
  - `sign_welcome.*` → `sign-welcome.*`
  - `sign_whimsicalfrog.*` → `sign-whimsicalfrog.*`
- Logos (`images/logos/`)
  - `logo_whimsicalfrog.*` → `logo-whimsicalfrog.*`

All code paths referencing these images were updated to match the new names. Additionally, dynamic background loaders normalize legacy names returned by APIs/DB to the `background-…` form.

## Rewrite Shims (Backward Compatibility)

Legacy URLs with underscores are mapped to the new dashed filenames via the root `.htaccess`:

```apache
# Back-compat: map legacy underscore image names to new dash-based names
<IfModule mod_rewrite.c>
  RewriteEngine On

  # backgrounds: background_* -> background-*
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^images/backgrounds/background_(.+)\.(webp|png)$ images/backgrounds/background-$1.$2 [R=302,L]

  # signs: sign_door_roomN -> sign-door-roomN
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^images/signs/sign_door_room([0-9]+)\.(webp|png)$ images/signs/sign-door-room$1.$2 [R=302,L]

  # common sign names
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^images/signs/sign_welcome\.(webp|png)$ images/signs/sign-welcome.$1 [R=302,L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^images/signs/sign_main\.(webp|png)$ images/signs/sign-main.$1 [R=302,L]
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^images/signs/sign_whimsicalfrog\.(webp|png)$ images/signs/sign-whimsicalfrog.$1 [R=302,L]

  # logos: logo_whimsicalfrog -> logo-whimsicalfrog
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^images/logos/logo_whimsicalfrog\.(webp|png)$ images/logos/logo-whimsicalfrog.$1 [R=302,L]
</IfModule>
```

Notes:
- We currently use `302` (temporary) while the migration settles. Switch to `301` (permanent) once all caches and references are confirmed updated.
- The shims only apply when the legacy file is not present on disk (`!-f`).

## Developer Workflow

- Adding a new background, sign, or logo:
  - Place under its respective folder.
  - Name lowercase, dash-only; backgrounds must be `background-…`.
  - Provide both `.webp` and `.png` variants.
- Adding a new item image (SKU):
  - Place under `images/items/`.
  - Uppercase allowed (SKU-style), prefer dashes, no underscores/spaces.
  - Provide both `.webp` and `.png` variants.
- Validate locally:
  - Run `npm run guard:images` and `npm run audit:images:code`.
  - Commit; pre-commit will enforce the same checks.

## Troubleshooting

- Guard fails with `[no-underscores]`: rename the file to use dashes.
- Guard fails with `[missing-pair]`: add the missing `.webp` or `.png` twin.
- Background not loading dynamically:
  - Check that the DB/API value is either already `background-…` or can be normalized. The dynamic loader normalizes `background_…` to `background-…` automatically.
- If a legacy URL still appears in code, the static audit should flag the missing file or missing pair.

## Rationale

- Dashes are the de facto web standard and avoid encoding issues.
- `.webp` brings significant performance benefits but `.png` is kept for compatibility and tooling simplicity.
- Pre-commit and CI integration ensures drift cannot regress standards.

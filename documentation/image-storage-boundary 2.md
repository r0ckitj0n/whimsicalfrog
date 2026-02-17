# Image Storage Boundary (`/images` vs `/dist/assets`)

## Single Rule
- Treat `/images` as runtime content (source of truth for media).
- Treat `/dist/assets` as build output only (never edited, never referenced directly in app code).

## Decision Delimiter
Put an image in `/images` if any of these are true:
- It is uploaded/managed by admin tools or APIs.
- It can change without a frontend rebuild.
- A database row points to it (item images, room backgrounds, signs, logos used by content settings).
- You need the same stable URL across deploys (for example `/images/items/<sku>.webp`).

Put an image in frontend source (`/src/...`) only if all are true:
- It is a static UI decoration owned by code.
- It changes only with code releases.
- It is not referenced by DB content.
- It is safe to fingerprint/hash and replace on every build.

`/dist/assets` should only contain compiled artifacts from Vite:
- JS/CSS bundles
- hashed static assets that originated from frontend source

## Usage Rules
- Do not hardcode `/dist/assets/...` anywhere.
- Runtime/content images should use `/images/...` URLs.
- Frontend static images should be imported from source and let Vite emit hashed paths.
- Keep fallback/product/background/sign placeholders in `/images/...` when they are content-domain assets.

## Current Audit Notes (Feb 2026)
- `/images` currently contains runtime media by domain (`backgrounds`, `items`, `signs`, `logos`) and should remain the canonical media store.
- `/dist/assets` had duplicated background files because Vite was aliasing `/images` as a source import path.
- `vite.config.ts` was updated to remove the `/images` alias so `/images/...` remains runtime URL space.
- `src/assets/cloud-bg.png` and `src/assets/cloud-bg.webp` are currently not referenced by source and appear to be leftovers.

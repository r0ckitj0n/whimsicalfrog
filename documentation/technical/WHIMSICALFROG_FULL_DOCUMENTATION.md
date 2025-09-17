> Note: Technical Reference — Historical/Deep Technical context. For current behavior and routes, see documentation/ADMIN_GUIDE.md.

# WhimsicalFrog Full Documentation

This is the central index for the WhimsicalFrog documentation. It aggregates links to subsystem guides and high‑level overviews.

## Table of Contents

- [Getting Started](#getting-started)
- [Architecture Overview](#architecture-overview)
- [Frontend (Vite) Conventions](#frontend-vite-conventions)
- [Backend (PHP) Conventions](#backend-php-conventions)
- [Tooling & Scripts](#tooling--scripts)
- [Admin Tooltip System](#admin-tooltip-system)
- [Other References](#other-references)

## Getting Started

See the project root `README.md` for quick start commands and environment setup.

## Architecture Overview

- Centralized database configuration and helpers live under `includes/` and are documented in `documentation/technical/CONSOLIDATION_PROJECT_SUMMARY.md`.
- Frontend is bundled with Vite and entry points live under `src/js/` and `src/styles/`.

## Frontend (Vite) Conventions

- Avoid inline styles; use source-level CSS in `src/styles/`.
- Prefer page-scoped modules wired via `src/js/app.js`.

## Backend (PHP) Conventions

- Use the centralized `Database` utilities from `api/config.php` and `includes/`.
- New endpoints should follow the JSON response conventions summarized in `documentation/technical/CONSOLIDATION_PROJECT_SUMMARY.md`.

## Tooling & Scripts

- NPM scripts for tooltips and CSS guards are defined in `package.json`.
- Shell/Node scripts live under `scripts/` and `scripts/dev/`.

## Admin Tooltip System

Tooltip CONTENT is dynamic from the database; styling/loader is Vite-managed static assets.

- Runtime: `src/modules/tooltip-manager.js` (auto-loaded on admin routes)
- Styles: `src/styles/components/tooltip.css`
- API: `api/help_tooltips.php` (`action=get`, `action=list_all`, `action=upsert`)
- Guide: see [Admin Tooltip System](#admin-tooltip-system) in this document
- Commands:
  - `npm run tooltips:export`
  - `npm run tooltips:audit`
  - `npm run tooltips:seed:dry`
  - `npm run tooltips:seed`

## Other References

- `documentation/technical/FILE_STRUCTURE_ORGANIZATION.md`
- `documentation/technical/CSS_CLASSES.md`
- `documentation/technical/FOOTER_SYSTEM_DOCUMENTATION.md`
- `documentation/technical/SEO_IMPLEMENTATION_GUIDE.md`

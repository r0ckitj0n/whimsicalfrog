# WhimsicalFrog Documentation

<sub><sup>Last updated: 2025-09-17</sup></sub>

This README serves as a consolidated entry point for project documentation. It reflects the current configuration after admin tooling and routing cleanup.

## Table of Contents

### Current Docs
- [Admin Guide](./ADMIN_GUIDE.md) — [Current]
- [Modal Conventions & Upgrade Guide](./technical/MODAL_CONVENTIONS_AND_UPGRADE_GUIDE.md) — [Current]
- [Database Helper Adoption Guide](./includes/DB_HELPER_ADOPTION.md) — [Current]

### Historical & Deep Technical (Archived or Context)
- No archived items currently listed. Refer to Git history if needed.

Other deep technical docs may remain under `documentation/` for reference; prefer the Admin Guide for current behavior and routes.

## Conventions
- All documentation lives under `documentation/`.
- Filenames are UPPER_SNAKE_CASE with underscores (no hyphens), except `README.md` in this folder.
- Use subfolders like `technical/`, `frontend/`, and `includes/` to group related docs.

## Notes
- Older docs referencing `/admin/admin.php?section=...` have been updated to the clean route `/admin/?section=...`.
- Some historical files are retained for context but the Admin Guide reflects the canonical, current setup.

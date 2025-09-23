# Admin UI Legacy Recovery Plan (June 29 look/feel)

Goal: Restore June 29 admin modal/tab visuals within Vite, class-based styling, no inline styles.

## Done
- Created `src/styles/admin/admin-legacy-modals.css` (ported container, spacing, scrollbars, buttons, inputs).
- Imported in `src/js/admin-inventory.js`.
- Overlay/stacking normalized in `src/styles/admin-modals.css`; diagnostics in module.

## Next
- Validate inventory modal layout vis-à-vis June 29.
- If needed: add optional `.admin-legacy-header` for gradient/title/close alignment.
- Apply compat import to admin settings entry/module; tweak per-modal as needed.
- Optional gate: wrap compat rules with `body.admin-legacy-skin` for selective rollout.
- QA: viewport fit, max-height, scroll, focus trap, pointer-events.
- Document component checklist; define acceptance criteria and rollback path.

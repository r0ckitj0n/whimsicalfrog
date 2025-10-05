# Pull Request

## Summary
- What does this PR change? Why?
- Link related issues/notes.

## Screenshots / Demos (if UI)
- Before/After or a short clip

## CSS Compliance Checklist
- [ ] Colors use canonical tokens
  - `var(--brand-primary)` for primary
  - `var(--brand-secondary)` for hover/secondary accents
  - Buttons: `--button-bg-primary`, `--button-bg-primary-hover`, `--button-text-primary`
- [ ] Fonts use variables
  - Display/brand: `var(--font-primary)`
  - Body/UI: `var(--font-secondary)`
  - Code/receipts: `var(--code-font-family)`
- [ ] Z-index uses tokens
  - `--z-overlay`, `--z-index-modal-content`, `--z-admin-overlay`, `--z-admin-overlay-content`, `--z-global-popup`
  - No raw numeric z-index values in new CSS
- [ ] Layout offsets use tokens
  - `--header-height`, `--overlay-offset`, `--header-offset`
- [ ] No inline styles added in templates or JS-generated markup

## Run Local Guards Before Pushing
Execute these commands locally and ensure no matches are reported:

```bash
npm run guard:styles           # Disallow inline styles
npm run guard:brand-and-fonts  # Disallow legacy greens; ensure font-family uses variables
npm run guard:z-index          # Disallow raw numeric z-index outside token files
npm run lint && npm run lint:css
```

## Documentation
- Refer to `documentation/frontend/CSS_COMPONENTIZATION_AND_CI.md` for the canonical brand/color, font, z-index, and layout token system and CI guard patterns.

## Additional Notes
- Any migrations from legacy `wf-*` tokens should prefer canonical tokens; legacy aliases are available for backward compatibility.

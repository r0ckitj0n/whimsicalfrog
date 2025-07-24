# Z-Index Structure and Guidelines

This document describes how z-index values are managed centrally in WhimsicalFrog.

## Central Variables (css/core/variables.css)

:root {
  /* Base stacking context */
  --z-base: 1;
  --z-content-overlay: 10;
  --z-dropdown: 100;
  --z-navigation: 200;
  --z-modal-backdrop: 300;
  --z-modal: 400;
  --z-notification: 500;
  --z-tooltip: 600;
  --z-debug: 9999;

  /* New centralized values */
  --z-popup: 2600;
  --z-image-viewer: 2700;
  --z-room-modals: 2400;
  --z-room-modal-header: 2450;
  --z-room-buttons: 55;
}

## Utility Classes (css/core/utilities.css)

Use the following classes instead of inline styles:

- `.z-popup` &nbsp;&nbsp;→ `z-index: var(--z-popup)`
- `.z-image-viewer` → `z-index: var(--z-image-viewer)`
- `.z-room-modals` → `z-index: var(--z-room-modals)`
- `.z-room-modal-header` → `z-index: var(--z-room-modal-header)`
- `.z-room-buttons` → `z-index: var(--z-room-buttons)`

## Room Modal Component Guidelines

To ensure consistent stacking and clarity in room modal overlays, apply the following utility classes:

- `.z-room-modals`: to the modal backdrop container.
- `.z-room-modal-header`: to modal header and title elements.
- `.z-room-buttons`: to navigation and action buttons within the modal (e.g., back and close buttons).

## Refactoring Guidelines

1. **Never** use magic numbers for `z-index` in CSS or JS.
2. In CSS, reference variables: `z-index: var(--z-modal);` etc.
3. In JS, add the appropriate utility class to elements instead of setting `style.zIndex`:
   ```js
   element.classList.add('z-popup');
   ```
4. If a new stacking context is needed, add a new variable with a clear name and update this document.
5. Remove any inline `style.zIndex` assignments and replace with the classes above.

## Maintenance

- Keep all z-index variables in `css/core/variables.css` under the **Z-INDEX SCALE** section.
- Update `documentation/ZIndexStructure.md` when variables or classes change.

---
_Last updated: 2025-07-23_

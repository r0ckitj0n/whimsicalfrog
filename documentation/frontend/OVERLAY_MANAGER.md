# Overlay Manager

A minimal utility exposed as `window.OverlayManager` to standardize modal overlay control.

- **API**
  - `OverlayManager.open(id: string): boolean`
    - Shows overlay with the given id. Falls back to a safe open if global helpers are unavailable.
  - `OverlayManager.close(id: string): boolean`
    - Hides overlay with the given id. Falls back to a safe close if global helpers are unavailable.
  - `OverlayManager.isVisible(id: string): boolean`
    - Returns `true` if the overlay is visible (`.show` present and not `.hidden`).

- **Events**
  - `wf:overlay:open` (window)
    - Fired when an overlay is shown.
    - `detail`: `{ id: string, element: HTMLElement, time: number }`
  - `wf:overlay:close` (window)
    - Fired when an overlay is hidden.
    - `detail`: `{ id: string, element: HTMLElement, time: number }`

- **A11y & UX**
  - Default open ensures `role="dialog"`, `aria-modal="true"`, and attempts to set `aria-labelledby` to the header id.
  - Focus is directed to the first actionable control; page scroll is locked via `wf-admin-modal-open`.
  - `admin-settings-lightweight.js` augments this with a focus trap, ESC handling, parent re-show, and single-scroll policy.

- **Usage**
  ```js
  // Open the AI Tools overlay
  OverlayManager.open('aiUnifiedModal');

  // Check visibility
  if (OverlayManager.isVisible('aiUnifiedModal')) {
    // Close
    OverlayManager.close('aiUnifiedModal');
  }

  // Listen for overlay lifecycle
  window.addEventListener('wf:overlay:open', (e) => {
    console.log('overlay open:', e.detail.id);
  });
  ```

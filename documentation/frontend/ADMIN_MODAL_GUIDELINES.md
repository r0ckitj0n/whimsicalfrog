# Admin Modal Guidelines

This document summarizes the conventions for admin overlays/modals.

- **Overlay portal**
  - Overlays are appended to `document.body`.
  - Use `admin-modal-overlay` and `admin-modal` wrappers.

- **Viewport anchoring**
  - Overlays fill viewport; panel is centered responsive (`admin-modal--responsive`).
  - Prefer panel/body height clamp to avoid double scrollbars.

- **Single-scroll policy**
  - Only one scrollbar visible at a time.
  - If content exceeds viewport, panel body scrolls; otherwise page remains locked.
  - Fallback lock class on `<html>, <body>`: `wf-admin-modal-open`.

- **Z-index & stacking**
  - Use token classes; child overlays use `topmost`.
  - Child overlay hides parent, and on close parent is re-shown.

- **Inline by default (no iframes)**
  - `window.__WF_INLINE_STRICT = true` enables inline as default.
  - Rollback flags (to force iframe) remain: `?frame=1`, `?wf_force_frame=1`, `window.__WF_FORCE_FRAME = true`, or `{ forceFrame: true }` in `postMessage`.

- **Child inline containers**
  - Parent hosts `#aiUnifiedChildModal` with:
    - `#aiUnifiedChildInline` for inline content
    - `#aiUnifiedChildFrame` for legacy iframe fallback
  - Each tool renders into a specific inline container id (e.g., `#intentHeuristicsContent`).

- **Open/close & focus management**
  - Focus the first actionable element on open.
  - Focus trap within the overlay while visible (Tab/Shift+Tab cycles within panel).
  - ESC closes overlay; focus returns to opener.
  - When a child overlay closes, the parent overlay reappears if it was previously visible.

- **A11y**
  - On show: ensure `role="dialog"`, `aria-modal="true"`, and `aria-labelledby` targets the header id.
  - Use `role="status"` + `aria-live="polite"` for transient status text.

- **Deep-linking**
  - Tools can open via query: `?wf_open=intent|suggestions|social|automation|content|newsletters|discounts|coupons`.

- **Rollback guards**
  - Force inline: `?wf_inline=1`, `?wf_inline_strict=1`, global `__WF_INLINE_STRICT`, or message `{ forceInline: true }`.
  - Force iframe: `?frame=1`, `?wf_force_frame=1`, page `?wf_marketing_force_frame=1`, or global `__WF_FORCE_FRAME`.

- **Testing**
  - Smoke test (puppeteer-core): `npm run smoke:overlays` with `BASE_URL`.

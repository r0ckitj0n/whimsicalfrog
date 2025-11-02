# Admin Modal Embed Autosize (Height + Width)

This document describes the finalized, site‑wide autosizing system for admin modals that contain embedded iframes. It covers the parent and child components, the centralized wiring in the modal show helper, and implementation guidance for adding new modals.

## Overview
- Parent page (host) listens for size messages from the iframe and applies dynamic CSS rules.
- Child page (inside iframe) measures its content and posts both height and width.
- A robust same‑origin fallback runs until a plausible message is received, ensuring first‑load sizing without user interaction.
- All wiring is centralized in `__wfShowModal`, so any admin modal opened through it gets autosizing automatically.

## Components
- Parent module: `src/modules/embed-autosize-parent.js`
  - One global message listener (origin aware).
  - Per‑iframe CSS rules generated in a single `<style id="wf-embed-dynamic-heights">` tag.
  - Height clamp: 98vh minus modal header/padding.
  - Width clamp: panel `max-width` at 70vw. Panel `width` is set only when measured content width is meaningfully below the clamp; otherwise max‑width governs.
  - Removes frame/panel static sizing classes that interfere (e.g., `wf-embed--fill`, size buckets).
  - Adds `.modal-body{ min-height: ... }` per panel to prevent collapsed bodies.
  - Same‑origin fallback burst on iframe load (0/50/100/200/350/500/800/1200ms) for stable first paint.
  - Ignores early/implausibly small child reports; keeps fallback active until a stable measurement arrives.

- Child module: `src/modules/embed-autosize-child.js`
  - Activates only when `body[data-embed='1']` is present (provided by `partials/modal_header.php`).
  - Measures a stable root node per page:
    - Categories: `#categoryManagementRoot` (preferred)
    - Marketing: `.admin-marketing-page`
    - Otherwise: first `.admin-card` → common admin containers → `body`.
  - Posts `{ source: 'wf-embed-size', height, width }` via `postMessage`.
  - Uses `ResizeObserver` and `scrollHeight/scrollWidth` with bounding‑rect fallback.

- Centralized wiring: `src/modules/admin-settings-lightweight.js`
  - `__wfShowModal(id)` performs standard wiring on every open:
    - Marks overlay responsive and removes scroll/fill classes that fight autosize.
    - For every iframe in the overlay, sets `data-autosize="1"`, clears `data-wf-use-msg-sizing`, and attaches same‑origin fallback.
    - MutationObserver wires iframes added after the modal opens.
  - All iframe modal handlers set the iframe `src` before showing the modal to avoid measuring `about:blank`.

## Implementation checklist for any admin iframe modal
1. The iframe page must include `partials/modal_header.php` (or equivalent) so `body[data-embed='1']` is set and `entries/app.js` loads the child autosizer.
2. The parent page must open the modal via `__wfShowModal(id)` so the overlay and iframes are wired automatically.
3. Set the iframe `src` before calling `__wfShowModal(id)` (if `src` is empty or `about:blank`).
4. Prefer a page‑specific, stable measurement root in the child for best first‑paint sizing.
5. Do not add fixed height/width classes to iframe or panel. The parent will remove common offenders if present.

## Debugging
- Enable logs in the console: `window.__WF_DEBUG = true`.
- On open, expect a quick same‑origin fallback burst, then a stable child message.
- In Elements, the `<style id="wf-embed-dynamic-heights">` tag should include:
  - `#<iframeId> { height: ... }`
  - `#<panelId> { max-width: ...; [width: ...] }`
  - `#<panelId> .modal-body { min-height: ... }`

## Notes
- Width is applied to the panel, not the iframe, for better layout behavior.
- Height is applied to the iframe.
- The system supports multiple iframes concurrently (rules keyed by auto‑assigned ids).
- All sizing is done via CSS (no inline width/height on elements) to keep code lint‑friendly.

## Files involved (key parts)
- `src/modules/embed-autosize-parent.js`
- `src/modules/embed-autosize-child.js`
- `src/modules/admin-settings-lightweight.js`
- `partials/modal_header.php`

## Adding a new modal (example)
- Create overlay markup with `.admin-modal-overlay` → `.admin-modal` → `.modal-header` + `.modal-body` containing an iframe.
- Ensure iframe `data-autosize="1"` (optional; the show helper will set it if missing).
- In the click handler:
  - Assign `src` if empty.
  - Call `__wfShowModal('myModalId')`.
- The rest is automatic.

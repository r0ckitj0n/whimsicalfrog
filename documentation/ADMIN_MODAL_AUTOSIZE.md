# Admin Modal Autosize Architecture

A standardized, reusable autosizing system for admin modals that embed pages via iframes.

- Parent module controls iframe height using postMessage + CSS.
- Child module measures content inside the iframe and emits height changes.
- CSS ensures embedded pages use intrinsic sizing (no viewport-stretching).
- Server flag marks modal context so child logic and CSS activate.

---

## Goals

- One consistent pattern for autosizing across all admin modals.
- No ad-hoc height code; no inline style churn.
- Source-level CSS managed by Vite; intrinsic sizing inside iframes.
- Secure origin validation for cross-document messaging.

---

## Parent (host window)

- Module: `src/modules/embed-autosize-parent.js`
- Exports:
  - `initEmbedAutosizeParent()`
  - `attachSameOriginFallback(iframe, overlay)`
  - `markOverlayResponsive(overlay)`

What it does:
- Listens for `postMessage({ source: 'wf-embed-size', height })` from child.
- Validates `ev.origin` as same-origin or same-host (dev ports allowed).
- Applies height via a single shared `<style id="wf-embed-dynamic-heights">`.
- Clamps to 95vh minus header + body padding; toggles modal body between
  `wf-modal-body--autoheight` and `wf-modal-body--scroll`.
- Stores the last applied height on the iframe (`data-wf-last-height`).
- Disables the same-origin fallback as soon as message-based sizing is active.

Integration example (already done):
- `src/modules/admin-settings-lightweight.js`
  - Import and initialize once near top:
    ```js
    import { initEmbedAutosizeParent, attachSameOriginFallback, markOverlayResponsive } from '../modules/embed-autosize-parent.js';
    initEmbedAutosizeParent();
    ```
  - Ensure modal overlays with autosized frames:
    - add `data-autosize="1"` to the iframe
    - call `markOverlayResponsive(overlay)`
    - call `attachSameOriginFallback(iframe, overlay)` for same-origin dev

---

## Child (embedded page)

- Module: `src/modules/embed-autosize-child.js`
- Behavior (activates only when embedded):
  - Runs IFF `window.parent !== window` and `<body data-embed="1">`.
  - Finds a stable measure node (prefers `.admin-card`, else top-level container).
  - Computes outer box height (rect + margins) to avoid scrollHeight jitter.
  - Uses `ResizeObserver` to emit height via `postMessage({ source: 'wf-embed-size', height })`.

How to load:
- For pages with a dedicated Vite entry, import:
  ```js
  import '../styles/embed-iframe.css';
  import '../modules/embed-autosize-child.js';
  ```
- For pages that use `partials/modal_header.php` and share `src/entries/app.js`:
  - `app.js` conditionally imports the child + CSS automatically when it detects `body[data-embed='1']`.
  - Minimal requirement: ensure `?modal=1` → `modal_header.php` is included.

---

## CSS for embedded pages

- File: `src/styles/embed-iframe.css`
- Scope:
  - `html:has(body[data-embed='1'])` and `body[data-embed='1']`
- Purpose: Make html/body intrinsic height, visible overflow, and neutralize forced full heights.
- Additional page-level guidance:
  - Avoid `html, body { height: 100% }` in modal context.
  - Avoid `max-height: 100vh` inside the embed; prefer natural content height.
  - Avoid stretching containers to fill the viewport; let content drive height.

---

## Server flag & header

- Use `?modal=1` to switch pages into embed mode.
- `partials/modal_header.php`:
  - Loads `app.js` or Vite HMR in dev.
  - Sets `<body data-embed="1" ...>` so child module + CSS activate automatically.
  - Preserves `data-page` when available.

Example pages (already modal-friendly):
- `sections/admin_marketing.php`
- `sections/tools/reports_browser.php`
- `sections/tools/template_manager.php`
- `sections/tools/md_viewer.php`
- `sections/tools/address_diagnostics.php`
- `components/embeds/attributes_manager.php`

---

## Security model

- Parent validates `ev.origin` against `window.location.origin` or same host in dev.
- Ignores messages not matching `{ source: 'wf-embed-size' }`.
- No sensitive data is exchanged—only measured height values are posted.

---

## Debugging tips

- Open the responsive admin modal and check the console:
  - Parent should log a bounded number of `[wf-embed-size] parent-message` lines.
- Inspect DOM:
  - `<style id="wf-embed-dynamic-heights">` contains a rule like `#myFrame{height:520px}`.
  - `.modal-body` toggles `wf-modal-body--autoheight` vs `wf-modal-body--scroll` with clamp.
  - Embedded document has `<body data-embed="1">` (modal_header.php) and intrinsic sizing.
- Common pitfalls:
  - Forced `height:100%` or `max-height:100vh` inside the embed → remove or scope to non-modal.
  - Child module not loaded: ensure the page uses `modal_header.php` or imports the child directly.

---

## Checklist (per responsive modal)

- Before open
  - Iframe has `data-autosize="1"` and a unique `id`.
- On open
  - Parent receives a few `wf-embed-size` messages; height rule appears in the dynamic style tag.
  - `.modal-body` is `wf-modal-body--autoheight` until content exceeds the clamp; then `wf-modal-body--scroll`.
- Child (inside iframe)
  - `<body data-embed="1">`; CSS reset applied; no elements stretch to viewport height.
  - `ResizeObserver` updates only on real content changes.

---

## Examples

Parent-side (JS):
```js
// In the modal open routine
const overlay = document.getElementById('myOverlay');
const iframe = overlay.querySelector('iframe');
iframe.setAttribute('data-autosize', '1');
markOverlayResponsive(overlay);
attachSameOriginFallback(iframe, overlay);
```

Child-side (JS entry):
```js
// In the embedded page entry
import '../styles/embed-iframe.css';
import '../modules/embed-autosize-child.js';
```

Server-side (PHP):
```php
$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';
if ($isModal) { include __DIR__ . '/../partials/modal_header.php'; }
```

---

## Converted so far

- Parent integrated in Admin Settings; iframes marked with `data-autosize`.
- Child and CSS imported globally for modal pages via app.js.
- Pages adjusted for intrinsic sizing (no forced 100%/100vh):
  - Attributes Manager (embed)
  - Categories Manager (host side)
  - Action Icons Manager (host + page CSS cleanup)
  - Email Settings (modal_header in modal context)
  - Receipt Messages (modal CSS: intrinsic sizing)
  - Address Diagnostics (modal_header in modal context)
  - Marketing (child + CSS via its entry)

Use this doc when converting additional modals. Follow the checklist and avoid page-level height forcing in embedded mode.

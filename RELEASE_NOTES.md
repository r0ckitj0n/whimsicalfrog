# Release Notes - 2025-09-02

## Summary
- Fix Vite dev proxy CSS MIME mismatch and stabilize HMR.
- Ensure Vite dev server binds to 127.0.0.1 for consistent WS connectivity.
- Improve proxy reliability (timeouts, connection handling, 127.0.0.1 fallback).

## Key Changes
- `vite-proxy.php`
  - Treat `/src/**/*.css` as module imports; append `?import` upstream.
  - Serve module-style CSS as `application/javascript`; plain CSS remains `text/css`.
  - Use short timeouts and `Connection: close` to avoid hanging sockets.
  - Prefer `127.0.0.1` over `localhost` to avoid IPv6 issues.
- `vite.config.js`
  - `server.host` and `server.hmr.host` set to `127.0.0.1`.

## Dev Notes
- Verified via curl: module CSS now returns `Content-Type: application/javascript`.
- HMR connects reliably (`[vite] connected`).
- Repository history scrubbed to remove a detected Anthropic API key; forced update to `main`.

## Next
- Prefer PR workflow for future changes (no direct pushes to `main`).
- Run end-to-end checks for room modals and login.

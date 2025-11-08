# Iframe Retirement Plan (Admin Overlays)

Objective: Remove legacy iframe code paths for admin overlays once inline paths are stable across supported tools and environments.

## Readiness Criteria
- Inline is default (`__WF_INLINE_STRICT = true`) and verified on:
  - Desktop (Chrome, Safari, Firefox) and at least one mobile browser.
  - Admin roles/permissions contexts (if applicable).
- E2E checks pass:
  - `npm run smoke:overlays` and `npm run test:overlays` against staging.
  - Resize clamp and single-scroll policy verified for all tools.
  - Keyboard accessibility: focus trap, ESC, close button, focus return.
- Observability: no elevated error rates in console/logs for overlay events.
- Rollback documented and available for one release cycle post-removal.

## Tools Covered
- Suggestions Manager
- Social Accounts Manager
- Automation Manager
- Content Generator
- Newsletters Manager
- Discounts Manager
- Coupons Manager
- Intent Heuristics Config

## Removal Steps
1. Announce deprecation timeline in release notes (1–2 versions ahead).
2. Freeze iframe fallback flags and code paths (warn once when used):
   - `?frame=1`, `?wf_force_frame=1`, `?wf_marketing_force_frame=1`, `window.__WF_FORCE_FRAME`.
3. Delete iframe-only CSS/JS with guarded commits:
   - Remove unused classes like `.wf-embed--fill` where solely serving old iframes.
   - Remove iframe autosize wiring where not used (keep embed-autosize for non-admin if still needed).
4. Simplify open-tool handler:
   - Drop fallback iframe branch; retain inline render + OverlayManager.
5. Update docs to remove fallback references.

## Rollback Plan (During Cooldown)
- Keep a feature branch that restores the iframe paths.
- If a regression is found:
  - Hotfix: re-enable fallback flags in code.
  - Rebuild and deploy; log usage to trace affected scenarios.

## Verification Checklist
- [ ] All inline tools open and render correct containers.
- [ ] Panel sizing: no double scrollbars; resize works.
- [ ] Keyboard: Tab/Shift+Tab trapped; ESC closes; focus returns.
- [ ] Parent re-shows after child close (if applicable).
- [ ] Live regions announce status messages (e.g., Intent Heuristics).
- [ ] E2E suite green on staging.

## Timeline Proposal
- Week 0–1: Run with inline default; monitor events and smoke tests.
- Week 2: Merge removal PR after sign-off; tag release; monitor.

## Notes
- Maintain OverlayManager and lifecycle events as the primary interface going forward.
- Prefer URL-driven deep-links for tool opening; keep `postMessage` handling for embedded use cases.

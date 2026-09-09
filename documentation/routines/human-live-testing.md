# Human Live Testing (Standing Preference)

**Owner preference (Jon Graves, 2026-09-09):** Human testing for this project (and Jon’s other low-traffic sites) is done on the **live production server**, not a local/dev stack or a separate staging environment.

## What agents must do

- When you need Jon to verify a change, **deploy to live first** using `bash scripts/deploy_agent_safe.sh` (see `agent-safe-deploy.md`).
- Give him the Live Site URL from `AGENTS.md` and concrete checks.
- Keep doing agent-side local/curl/browser QA before deploy.

## What agents must not do

- Do not treat “please test on a local Vite/dev stack / pull the branch” as the default human handoff.
- Do not use destructive full-tree deploys (`deploy_full.sh`, `--purge*`, root `mirror --delete`).

## Why

Sites do not get enough traffic to justify a parallel human staging workflow. Live is the practical QA surface.

Canonical rule text also lives in `AGENTS.md` under **Human Live Testing Mandate** and **Live Deploy Standing Process**.

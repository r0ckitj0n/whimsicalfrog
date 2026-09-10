# Live Deploy Policy

## What you are looking at in GitHub

There are two different UIs:

1. **Actions → Deploy to IONOS** — the real deploy job. This is what uploads to the live site.
2. **Environments / Deployments** — only updates when the workflow declares `environment: production`.

Before this policy change, the workflow ran file deploys but did **not** create GitHub Deployment records, so the Environments page could look frozen on an old date even while Actions kept deploying.

## Default policy: live has precedence

Routine pushes to `main` now deploy with `--security-only`:

- Force-upload only the auth/session allowlist in `scripts/deploy/security_allowlist.txt`
- Do **not** mirror the whole tree
- Do **not** delete remote files
- Do **not** touch images, `dist/`, `.env`, or the database

That means live content stays in place unless a path is explicitly allowlisted as a security fix.

## When to use each mode

| Mode | Command / workflow input | Use when |
|------|--------------------------|----------|
| `security-only` (default) | `./scripts/deploy.sh --security-only` | Auth/session/CORS security fixes only |
| `lite` | workflow_dispatch → `lite`, or `./scripts/deploy.sh --lite` | Intentional broad code sync (can overwrite live files and sync `dist/`) |
| `dist-only` | `./scripts/deploy.sh --dist-only` | Frontend asset publish only |
| `env-only` | `./scripts/deploy.sh --env-only` | Upload `.env.live` → live `.env` |
| `full` | `./scripts/deploy_full.sh` | Dangerous: can restore local DB onto live |

## Adding a new security-critical path

1. Land the fix on `main` through a PR.
2. Add the relative path to `scripts/deploy/security_allowlist.txt`.
3. Merge. The next `main` push auto-deploys that allowlist only.

## Manual deploy

```bash
# Preferred for agents / routine publishes (backup-first, no live-data deletes)
./scripts/deploy_agent_safe.sh --frontend
./scripts/deploy_agent_safe.sh --code

# Auth/session allowlist only
./scripts/deploy.sh --security-only

# Intentional broad code sync (can overwrite non-image live files; prefer agent-safe instead)
./scripts/deploy.sh --lite
```

Required secrets / `.env` keys: `WF_DEPLOY_HOST`, `WF_DEPLOY_USER`, `WF_DEPLOY_PASS`.

See also: `documentation/routines/agent-safe-deploy.md`.

## Pulling live content back into local / Cloud Agent

Live is the source of truth for uploaded images and any on-server hotfixes. Prefer live-newer mirrors over pushing stale checkout trees.

```bash
# Images only (default; Cloud Agent start.sh does this every boot)
bash scripts/cloud/sync_from_live.sh --images

# Images + code trees (api/includes/src/…)
bash scripts/cloud/sync_from_live.sh --all

# DB restore + file sync helper
bash scripts/cloud/pull_live_backup.sh --files
```

These mirrors use `--only-newer` and never delete local files. Soft-skip with `--soft` when deploy credentials are missing.

## Image upload policy on deploy

- `deploy_agent_safe.sh` never uploads or deletes `images/`.
- `deploy.sh` defaults to **live image precedence**: image uploads are skipped unless you pass `--push-images` (or set `WF_PUSH_IMAGES=1`).
- Before an intentional image push, refresh local from live first so you do not overwrite newer live files with older git-checkout copies (git refresh resets mtimes).

## Important note about Aug 2026 merges

Several `main` merges before this policy change ran the old `--lite` Action. Those runs did overwrite security PHP files (intended) and refreshed `dist/`. Going forward, automatic deploys stay on `--security-only` unless you explicitly choose `lite` in **Actions → Deploy to IONOS → Run workflow**.

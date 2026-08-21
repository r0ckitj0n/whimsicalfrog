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
# Preferred / safe
./scripts/deploy.sh --security-only

# Intentional full code sync (overwrites non-image live files)
./scripts/deploy.sh --lite
```

Required secrets / `.env` keys: `WF_DEPLOY_HOST`, `WF_DEPLOY_USER`, `WF_DEPLOY_PASS`.

## Pulling live content back into local

If live has newer content you want in git:

```bash
bash scripts/cloud/pull_live_backup.sh --files
```

That mirrors live → local with `--only-newer` and no deletes.

## Important note about Aug 2026 merges

Several `main` merges before this policy change ran the old `--lite` Action. Those runs did overwrite security PHP files (intended) and refreshed `dist/`. Going forward, automatic deploys stay on `--security-only` unless you explicitly choose `lite` in **Actions → Deploy to IONOS → Run workflow**.

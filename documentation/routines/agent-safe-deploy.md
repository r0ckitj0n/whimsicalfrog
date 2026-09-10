# Agent-Safe Live Deploy (Standing Process)

## Why this exists

Live often has newer content than a cloud agent workspace (images, `.env`, customer data, on-server backups). Agents must **never** wipe or blindly mirror over live-owned data.

Use this process for every agent-driven live publish.

## Command

```bash
# Frontend / React UI changes (this is the usual case after merging UI work)
bash scripts/deploy_agent_safe.sh --frontend

# PHP/API/includes/src code sync without deleting live-only files
bash scripts/deploy_agent_safe.sh --code

# Explicit allowlisted paths only
bash scripts/deploy_agent_safe.sh --paths api/foo.php includes/bar.php
```

Useful flags:

- `--dry-run` — print planned lftp actions only
- `--skip-build` — reuse existing `dist/`
- `--skip-db-backup` — skip live backup API calls (file snapshot still runs)

## Guarantees

1. **Backup first**
   - Downloads a snapshot of the remote paths about to change into `backups/pre-deploy/<timestamp>/live/`
   - Writes metadata under local + remote `backups/pre-deploy/<timestamp>/`
   - When `WF_ADMIN_TOKEN` + `WF_DEPLOY_BASE_URL` are set, also triggers live `backup_database.php` / `backup_website.php`
2. **No live-data deletes**
   - Never deletes/overwrites: `.env`, `images/`, `backups/` (except additive metadata), `sessions/`, `logs/`, `data/`, `node_modules/`, `.git/`, `vendor/`
3. **Scoped uploads**
   - `--frontend` only publishes `dist/` (+ `index.html` + room header source)
   - `--delete` is allowed **only inside `dist/`** so stale hashed bundles can be cleaned
   - `--code` / `--paths` use **no `--delete`**
4. **Verification**
   - Checks homepage + Vite manifest HTTP status
   - For frontend deploys, probes the live `RoomModal` chunk when present

## Required secrets / env

- `WF_DEPLOY_HOST`
- `WF_DEPLOY_USER`
- `WF_DEPLOY_PASS`
- Optional: `WF_DEPLOY_PATH`, `WF_DEPLOY_BASE_URL`, `WF_ADMIN_TOKEN`

## What NOT to run for routine agent deploys

- `scripts/deploy.sh --full` / `--purge*` — destructive
- `scripts/deploy_full.sh` — can restore DB onto live
- Whole-tree `lftp mirror --delete` against the site root

Prefer:

- `scripts/deploy_agent_safe.sh` for agent publishes
- `scripts/deploy.sh --security-only` for auth/session allowlist hotfixes only
- Explicit GitHub Actions `workflow_dispatch` modes when humans choose them

## Rollback

Local snapshot:

```text
backups/pre-deploy/<timestamp>/live/
```

Re-upload specific files from that snapshot with:

```bash
bash scripts/deploy_agent_safe.sh --paths path/to/file
```

(or restore via live backup APIs / `scripts/restore_from_backup.sh` when appropriate)

## Backgrounds / signs (Hard Rule)

Do **not** upload `images/backgrounds/**` or `images/signs/**` from agent checkouts. Fresh mtimes on older cartoon fallbacks have overwritten live realistic art.

- Normal agent deploys: use `scripts/deploy_agent_safe.sh` (excludes `images/`).
- `scripts/deploy.sh` skips backgrounds/signs unless `WF_ALLOW_BACKGROUND_PUSH=1` / `WF_ALLOW_SIGN_PUSH=1`.
- Refresh local media from live: `bash scripts/pull_live_backgrounds.sh`
- Intentional restore to live: `WF_ALLOW_BACKGROUND_PUSH=1 bash scripts/push_live_backgrounds.sh`


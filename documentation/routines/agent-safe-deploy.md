# Agent-Safe Live Deploy (Standing Process)

## Why this exists

Live often has newer content than a cloud agent workspace (images, `.env`, customer data, on-server backups). Agents must **never** wipe or blindly mirror over live-owned data.

Use this process for every agent-driven live publish.

### Human testing on live (Jon)

Jon QA’s on **live production** (Live Site URL in `AGENTS.md`), not a local/dev stack or staging. Traffic is low enough that this is intentional.

When an agent wants Jon to test a change:

1. Finish agent-side verification (build/lint/curl/browser as applicable).
2. Deploy with this script (`--frontend`, `--code`, or `--paths`).
3. Point him at the Live Site URL and what to check.

Do **not** ask him to run a local stack as the primary human test path.

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

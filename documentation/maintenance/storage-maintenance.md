# Storage Maintenance Playbook

## 1) Automatic Log Rotation (`/logs`)

Log maintenance is now run automatically by `scripts/server_monitor.sh`:

- On every `start` action.
- Periodically in `daemon` mode (default every 10 monitor loops).

Rotation behavior (`scripts/maintenance/rotate_logs.sh`):

- Rotates `*.log`, `*.out`, `*.err` files in `/logs` larger than `50MB`.
- Truncates active log files in place (safe for running processes).
- Keeps 7 compressed rotations per file.
- Prunes `/logs/screenshots/*` directories older than 14 days.

Tunable environment variables:

- `WF_LOG_ROTATE_MAX_SIZE_MB` (default `50`)
- `WF_LOG_ROTATE_NUM_KEEP` (default `7`)
- `WF_LOG_SCREENSHOT_RETENTION_DAYS` (default `14`)
- `WF_LOG_ROTATE_INTERVAL_LOOPS` (default `10`)

Manual run:

```bash
npm run logs:rotate
```

## 2) Backup Retention

Use:

```bash
npm run backups:retain:dry
npm run backups:retain
```

Script: `scripts/maintenance/retain_backups.sh`

Defaults:

- Keep all backup artifacts from last 7 days.
- For older backups, keep one artifact per week for 8 weeks.
- Dry-run by default; `--apply` actually deletes.

Managed locations:

- `backups/` (top-level backup artifacts only)
- `backups/live_sync/`
- `backups/local_pre_restore/`
- `backups/sql/`

Tunable environment variables:

- `WF_BACKUP_DAILY_DAYS` (default `7`)
- `WF_BACKUP_WEEKLY_WEEKS` (default `8`)

## 3) One-Time Git History Slimming (Optional Rewrite)

Analyze first:

```bash
npm run git:slim:analyze
```

Rewrite only when ready:

```bash
npm run git:slim:rewrite
```

Script: `scripts/maintenance/git_history_slim.sh`

Safety controls built in:

- Requires explicit rewrite confirmation flags.
- Refuses to run on dirty working trees.
- Creates a rollback `git bundle` in `.local/state/git-history/`.
- Creates a safety branch `codex/pre-history-slim-<timestamp>`.

Paths removed from full history during rewrite:

- `logs/`
- `dist/`
- `node_modules/`
- `.cache/`
- `backups/live_sync/`
- `backups/local_pre_restore/`
- `backups/sql/`
- top-level `backups/*.sql*`, `backups/*.tar*`, `backups/*.tgz`, `backups/*.zip`

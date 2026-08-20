#!/usr/bin/env bash
# Pull a full backup from the LIVE Whimsical Frog site and restore it locally.
#
# The live MySQL host is not directly reachable from most networks, so the
# database backup is produced server-side through the admin maintenance API and
# then downloaded over SFTP. Newer live files can optionally be mirrored into
# the local working tree so they take precedence over local copies.
#
# Required secrets (from .env or environment):
#   WF_ADMIN_TOKEN                      - authorizes the live maintenance API
#   WF_DEPLOY_HOST/USER/PASS           - SFTP credentials for the live server
# Optional:
#   WF_DEPLOY_BASE_URL                 - live base URL (default https://whimsicalfrog.us)
#   WF_REMOTE_BACKUP_DIR               - remote dir holding the dump (default backups)
#   WF_DB_LOCAL_*                      - local DB target (defaults: 127.0.0.1/whimsicalfrog/root)
#
# Usage:
#   scripts/cloud/pull_live_backup.sh              # DB backup + local restore
#   scripts/cloud/pull_live_backup.sh --files      # also mirror newer live files
#   scripts/cloud/pull_live_backup.sh --files-only # only mirror files, skip DB
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

log() { printf '[pull-live] %s\n' "$*"; }
die() { printf '[pull-live][error] %s\n' "$*" >&2; exit 1; }

# Load .env
if [[ -f "$ROOT_DIR/.env" ]]; then
  set -a; . "$ROOT_DIR/.env"; set +a
fi

DO_DB=1
DO_FILES=0
for arg in "$@"; do
  case "$arg" in
    --files) DO_FILES=1 ;;
    --files-only) DO_FILES=1; DO_DB=0 ;;
    --skip-db) DO_DB=0 ;;
    -h|--help) grep -E '^#( |$)' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) die "unknown argument: $arg" ;;
  esac
done

require() { [[ -n "${!1:-}" ]] || die "$1 must be set (add it as a secret / in .env)"; }

BASE_URL="${WF_DEPLOY_BASE_URL:-https://whimsicalfrog.us}"
REMOTE_BACKUP_DIR="${WF_REMOTE_BACKUP_DIR:-backups}"
LOCAL_DB_HOST="${WF_DB_LOCAL_HOST:-127.0.0.1}"
LOCAL_DB_PORT="${WF_DB_LOCAL_PORT:-3306}"
LOCAL_DB_NAME="${WF_DB_LOCAL_NAME:-whimsicalfrog}"
LOCAL_DB_USER="${WF_DB_LOCAL_USER:-root}"
LOCAL_DB_PASS="${WF_DB_LOCAL_PASS:-}"

mkdir -p "$ROOT_DIR/backups/sql"

# --- Database: create backup on live, download, restore locally -------------
if [[ "$DO_DB" -eq 1 ]]; then
  require WF_ADMIN_TOKEN
  require WF_DEPLOY_HOST
  require WF_DEPLOY_USER
  require WF_DEPLOY_PASS

  log "Requesting server-side backup via maintenance API"
  RESP_FILE="$(mktemp)"
  HTTP_CODE=$(curl -sS -o "$RESP_FILE" -w '%{http_code}' -X POST \
    "${BASE_URL%/}/api/database_maintenance.php?action=create_backup&admin_token=${WF_ADMIN_TOKEN}" \
    || printf '000')
  RESP_BODY="$(cat "$RESP_FILE")"; rm -f "$RESP_FILE"
  [[ "$HTTP_CODE" == "200" ]] || die "create_backup failed (HTTP $HTTP_CODE): $RESP_BODY"

  REMOTE_FILE="$(printf '%s' "$RESP_BODY" | grep -oE '"filename"[[:space:]]*:[[:space:]]*"[^"]+"' | head -1 | sed -E 's/.*"([^"]+)"$/\1/')"
  [[ -n "$REMOTE_FILE" ]] || die "Could not parse backup filename from: $RESP_BODY"
  log "Live backup created: $REMOTE_FILE"

  LOCAL_DUMP="$ROOT_DIR/backups/sql/$REMOTE_FILE"
  log "Downloading backup over SFTP"
  lftp -u "${WF_DEPLOY_USER},${WF_DEPLOY_PASS}" "sftp://${WF_DEPLOY_HOST}" <<LFTP
set sftp:auto-confirm yes
set ssl:verify-certificate no
set net:timeout 30
set net:max-retries 2
get -c "${REMOTE_BACKUP_DIR}/${REMOTE_FILE}" -o "${LOCAL_DUMP}"
bye
LFTP
  [[ -s "$LOCAL_DUMP" ]] || die "Downloaded dump is empty: $LOCAL_DUMP"
  log "Downloaded: $LOCAL_DUMP ($(du -h "$LOCAL_DUMP" | cut -f1))"

  log "Restoring into local database '${LOCAL_DB_NAME}'"
  if [[ -n "$LOCAL_DB_PASS" ]]; then export MYSQL_PWD="$LOCAL_DB_PASS"; fi
  mysql --protocol=TCP -h "$LOCAL_DB_HOST" -P "$LOCAL_DB_PORT" -u "$LOCAL_DB_USER" \
    -e "CREATE DATABASE IF NOT EXISTS \`${LOCAL_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql --protocol=TCP -h "$LOCAL_DB_HOST" -P "$LOCAL_DB_PORT" -u "$LOCAL_DB_USER" \
    --default-character-set=utf8mb4 "$LOCAL_DB_NAME" < "$LOCAL_DUMP"
  log "Database restore complete."
fi

# --- Files: mirror newer live files (live takes precedence) -----------------
if [[ "$DO_FILES" -eq 1 ]]; then
  require WF_DEPLOY_HOST
  require WF_DEPLOY_USER
  require WF_DEPLOY_PASS

  log "Mirroring newer live files into local tree (live precedence, --only-newer)"
  # Exclude VCS, build output, dependencies, local runtime state, and backups.
  lftp -u "${WF_DEPLOY_USER},${WF_DEPLOY_PASS}" "sftp://${WF_DEPLOY_HOST}" <<LFTP
set sftp:auto-confirm yes
set ssl:verify-certificate no
set net:timeout 30
set net:max-retries 2
mirror --only-newer --no-perms --verbose \
  --exclude-glob .git/ \
  --exclude-glob node_modules/ \
  --exclude-glob dist/ \
  --exclude-glob vendor/ \
  --exclude-glob backups/ \
  --exclude-glob logs/ \
  --exclude-glob .env \
  --exclude-glob hot \
  / "$ROOT_DIR"
bye
LFTP
  log "File mirror complete. Review changes with 'git status' before committing."
fi

log "Done."

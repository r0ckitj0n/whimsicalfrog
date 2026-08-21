#!/usr/bin/env bash
# Pull a full backup from the LIVE Whimsical Frog site and restore it locally.
#
# The live MySQL host is not directly reachable from most networks, and the
# admin API's create_backup writes a file that requires SFTP to retrieve. This
# script therefore pulls the database over HTTPS using the read-only
# `export_tables` action (batched to stay under the 50-table limit), sanitizes
# MySQL-8-only collations for MariaDB, and restores into the local DB.
#
# Newer live files can optionally be mirrored via SFTP (WF_DEPLOY_* creds).
#
# Required:
#   WF_ADMIN_TOKEN                     - authorizes the live maintenance API
# Optional (only for --files):
#   WF_DEPLOY_HOST/USER/PASS          - SFTP credentials for the live server
# Optional overrides:
#   WF_DEPLOY_BASE_URL                - live base URL (default https://whimsicalfrog.us)
#   WF_DB_LOCAL_*                     - local DB target (defaults 127.0.0.1/wf_local/root)
#
# Usage:
#   scripts/cloud/pull_live_backup.sh              # DB backup + local restore over HTTPS
#   scripts/cloud/pull_live_backup.sh --files      # also mirror newer live files (needs SFTP)
#   scripts/cloud/pull_live_backup.sh --files-only # only mirror files, skip DB
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

log() { printf '[pull-live] %s\n' "$*"; }
die() { printf '[pull-live][error] %s\n' "$*" >&2; exit 1; }

[[ -f "$ROOT_DIR/.env" ]] && { set -a; . "$ROOT_DIR/.env"; set +a; }

DO_DB=1; DO_FILES=0
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
API="${BASE_URL%/}/api/database_maintenance.php"
LOCAL_DB_HOST="${WF_DB_LOCAL_HOST:-127.0.0.1}"
LOCAL_DB_PORT="${WF_DB_LOCAL_PORT:-3306}"
LOCAL_DB_NAME="${WF_DB_LOCAL_NAME:-wf_local}"
LOCAL_DB_USER="${WF_DB_LOCAL_USER:-root}"
LOCAL_DB_PASS="${WF_DB_LOCAL_PASS:-}"
BATCH_SIZE=40

mkdir -p "$ROOT_DIR/backups/sql"

mysql_local() {
  if [[ -n "$LOCAL_DB_PASS" ]]; then MYSQL_PWD="$LOCAL_DB_PASS" mysql --protocol=TCP -h "$LOCAL_DB_HOST" -P "$LOCAL_DB_PORT" -u "$LOCAL_DB_USER" "$@";
  else mysql --protocol=TCP -h "$LOCAL_DB_HOST" -P "$LOCAL_DB_PORT" -u "$LOCAL_DB_USER" "$@"; fi
}

sanitize() { sed -e 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g' -e 's/utf8mb4_0900_as_cs/utf8mb4_unicode_ci/g'; }

export_tables_to() { # $1=comma-list  $2=outfile
  curl -fsS "${API}?action=export_tables&admin_token=${WF_ADMIN_TOKEN}&tables=${1}" --max-time 240 -o "$2"
}

if [[ "$DO_DB" -eq 1 ]]; then
  require WF_ADMIN_TOKEN
  TS="$(date +%F_%H-%M-%S)"
  DUMP="$ROOT_DIR/backups/sql/live_export_${TS}.sql"

  log "Fetching live table list (get_schema)"
  SCHEMA_JSON="$(mktemp)"
  curl -fsS "${API}?action=get_schema&admin_token=${WF_ADMIN_TOKEN}" --max-time 120 -o "$SCHEMA_JSON"
  mapfile -t TABLES < <(python3 -c "import json,sys;print('\n'.join(t['name'] for t in json.load(open('$SCHEMA_JSON')).get('tables',[])))")
  rm -f "$SCHEMA_JSON"
  [[ ${#TABLES[@]} -gt 0 ]] || die "No tables returned by get_schema (check WF_ADMIN_TOKEN)"
  log "Live reports ${#TABLES[@]} tables; exporting in batches of ${BATCH_SIZE}"

  {
    echo "-- WhimsicalFrog LIVE export ($(date))"
    echo "SET FOREIGN_KEY_CHECKS=0;"
    echo "SET NAMES utf8mb4;"
  } > "$DUMP"

  batch=(); i=0; bn=0
  flush() {
    [[ ${#batch[@]} -eq 0 ]] && return 0
    bn=$((bn+1)); local list; list=$(IFS=,; echo "${batch[*]}"); local f="$(mktemp)"
    export_tables_to "$list" "$f" || die "export batch $bn failed"
    cat "$f" | sanitize >> "$DUMP"; rm -f "$f"
    log "  batch $bn: ${#batch[@]} tables"
    batch=()
  }
  for t in "${TABLES[@]}"; do batch+=("$t"); i=$((i+1)); (( i % BATCH_SIZE == 0 )) && flush; done
  flush
  echo "SET FOREIGN_KEY_CHECKS=1;" >> "$DUMP"

  log "Restoring into local database '${LOCAL_DB_NAME}' (drop + recreate for an exact mirror)"
  mysql_local -e "DROP DATABASE IF EXISTS \`${LOCAL_DB_NAME}\`; CREATE DATABASE \`${LOCAL_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql_local --default-character-set=utf8mb4 "$LOCAL_DB_NAME" < "$DUMP"

  # Retry any tables that didn't make it (occasionally a batch truncates).
  mapfile -t LOCAL_TABLES < <(mysql_local -N "$LOCAL_DB_NAME" -e "SELECT table_name FROM information_schema.tables WHERE table_schema='${LOCAL_DB_NAME}'")
  MISSING=(); for t in "${TABLES[@]}"; do printf '%s\n' "${LOCAL_TABLES[@]}" | grep -qx "$t" || MISSING+=("$t"); done
  if [[ ${#MISSING[@]} -gt 0 ]]; then
    log "Retrying ${#MISSING[@]} missing tables: ${MISSING[*]}"
    f="$(mktemp)"; export_tables_to "$(IFS=,; echo "${MISSING[*]}")" "$f"
    { echo "SET FOREIGN_KEY_CHECKS=0; SET NAMES utf8mb4;"; cat "$f" | sanitize; echo "SET FOREIGN_KEY_CHECKS=1;"; } | mysql_local --default-character-set=utf8mb4 "$LOCAL_DB_NAME"
    rm -f "$f"
  fi

  FINAL=$(mysql_local -N "$LOCAL_DB_NAME" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${LOCAL_DB_NAME}'")
  log "Restore complete: ${FINAL}/${#TABLES[@]} tables. Dump saved at ${DUMP}"
fi

if [[ "$DO_FILES" -eq 1 ]]; then
  require WF_DEPLOY_HOST; require WF_DEPLOY_USER; require WF_DEPLOY_PASS
  log "Mirroring newer live files (live precedence, --only-newer)"
  lftp -u "${WF_DEPLOY_USER},${WF_DEPLOY_PASS}" "sftp://${WF_DEPLOY_HOST}" <<LFTP
set sftp:auto-confirm yes
set ssl:verify-certificate no
set net:timeout 30
set net:max-retries 2
mirror --only-newer --no-perms --verbose \
  --exclude-glob .git/ --exclude-glob node_modules/ --exclude-glob dist/ \
  --exclude-glob vendor/ --exclude-glob backups/ --exclude-glob logs/ \
  --exclude-glob .env --exclude-glob hot \
  / "$ROOT_DIR"
bye
LFTP
  log "File mirror complete. Review with 'git status' before committing."
fi

log "Done."

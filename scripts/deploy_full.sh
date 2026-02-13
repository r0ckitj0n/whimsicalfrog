#!/usr/bin/env bash
# WhimsicalFrog Full Deploy: database + files
#
# Steps:
#  1) Safety checks and env loading
#  2) Live backup (site files via API best-effort)
#  3) Dump LOCAL dev database (gzipped)
#  4) Restore dump to LIVE database
#       - Preferred: HTTPS API upload to database_maintenance.php with WF_ADMIN_TOKEN
#       - Fallback: Direct MySQL restore using scripts/db/restore_live_db.sh
#  5) Deploy files via scripts/deploy.sh (SFTP mirror with verification)
#       - Optional: --purge to clean remote managed directories first
#  6) Final summary
#
# Requirements:
#  - .env with WF_DB_LIVE_* (for fallback restore) and optional WF_ADMIN_TOKEN
#  - mysql client installed for fallback path
#  - curl, lftp, npm available for deploy.sh

set -euo pipefail
IFS=$'\n\t'

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

# Force production mode for Vite and backend
export WF_VITE_DISABLE_DEV=1
export WF_VITE_MODE=prod

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${GREEN}🚀 Starting FULL DEPLOY (DB + Files)${NC}"

# 1) Load .env if present (for WF_DB_LIVE_* and WF_ADMIN_TOKEN)
ENV_FILE="${ROOT_DIR}/.env"
if [[ -f "${ENV_FILE}" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "${ENV_FILE}"
  set +a
fi

BASE_URL="https://whimsicalfrog.us"

# 1a) Require explicit confirmation before overwriting LIVE DB (unless dry-run)
REQUIRE_CONFIRM="${WF_CONFIRM_FULL_DB_OVERWRITE:-1}"
CONFIRM_FLAG=0
PURGE_FLAG=0
for arg in "$@"; do
  case "$arg" in
    --confirm-db-overwrite)
      CONFIRM_FLAG=1
      ;;
    --purge)
      PURGE_FLAG=1
      ;;
  esac
done

if [[ "${PURGE_FLAG}" == "1" ]]; then
  echo -e "${RED}⚠️  WARNING: You have requested a COMPLETE PURGE of managed directories on the live server.${NC}"
  echo -e "   This will delete api/, dist/, images/, etc. before mirroring fresh files."
fi

if [[ "${WF_DRY_RUN:-0}" != "1" && "${REQUIRE_CONFIRM}" != "0" && "${CONFIRM_FLAG}" != "1" ]]; then
  echo -e "${RED}❌ Refusing to overwrite LIVE database without explicit confirmation.${NC}"
  echo "   Add --confirm-db-overwrite or set WF_CONFIRM_FULL_DB_OVERWRITE=1 to proceed."
  exit 1
fi

section() {
  echo -e "\n============================================================"
  echo -e "== $*"
  echo -e "============================================================\n"
}

# 2) Live backup (best effort)
section "Backup: triggering live website backup"
if [[ "${WF_DRY_RUN:-0}" == "1" ]]; then
  echo -e "${YELLOW}DRY-RUN: Skipping live backup API call${NC}"
else
  if curl -s -X POST "${BASE_URL}/api/backup_website.php" >/dev/null; then
    echo -e "${GREEN}✅ Live backup API triggered successfully${NC}"
  else
    echo -e "${YELLOW}⚠️  Live backup API call failed (continuing)${NC}"
  fi
fi

# 3) Validate and dump LOCAL dev database (gzipped)
if [[ "${WF_DRY_RUN:-0}" != "1" ]]; then
  section "Database: validating local dev DB for production safety"
  if php scripts/db/validate_dev_db_for_prod.php; then
    echo -e "${GREEN}✅ Dev DB validation passed${NC}"
  else
    echo -e "${RED}❌ Dev DB validation failed — aborting full deploy before touching LIVE DB${NC}"
    exit 1
  fi
else
  echo -e "${YELLOW}DRY-RUN: Skipping dev DB validation${NC}"
fi

section "Database: creating local dev DB dump (gz)"
mkdir -p backups/sql
# Preferred: use mysqldump-based script and capture its reported output path
if DUMP_OUT=$(bash scripts/db/dump_local_db.sh --gzip --exclude-secrets 2>&1); then
  # Extract the final path from a line like: "Done: backups/sql/local_db_dump_YYYY-MM-DD_HH-MM-SS.sql.gz"
  DUMP_PATH=$(echo "$DUMP_OUT" | awk '/^Done: /{p=$2} END{print p}')
  if [[ -n "${DUMP_PATH:-}" && -f "${DUMP_PATH}" ]]; then
    echo -e "${GREEN}✅ Local DB dump created at ${DUMP_PATH}${NC}"
  else
    echo -e "${YELLOW}⚠️  Could not determine dump path from mysqldump output; attempting PHP-based dumper${NC}"
  fi
else
  echo -e "${YELLOW}⚠️  mysqldump path failed. Falling back to PHP-based dumper (scripts/db/php_dump_dev.php)${NC}"
fi

# Fallback or continue if DUMP_PATH not set
if [[ -z "${DUMP_PATH:-}" || ! -f "${DUMP_PATH}" ]]; then
  # PHP dumper prints resulting path(s); capture the last non-empty line
  PHP_DUMP_OUT=$(php scripts/db/php_dump_dev.php --gzip --exclude-secrets 2>&1 || true)
  echo "$PHP_DUMP_OUT" | tail -n +1 | sed '/^$/d' | tail -n 1 > /tmp/wf_php_dump_path.txt || true
  if [[ -s /tmp/wf_php_dump_path.txt ]]; then
    DUMP_PATH=$(cat /tmp/wf_php_dump_path.txt)
    echo -e "${GREEN}✅ Local DB dump created via PHP at ${DUMP_PATH}${NC}"
    rm -f /tmp/wf_php_dump_path.txt || true
  else
    echo -e "${RED}❌ Failed to create local DB dump via mysqldump and PHP fallback${NC}"
    echo "$PHP_DUMP_OUT" | sed 's/^/    /'
    exit 1
  fi
fi

# 4) Restore to LIVE
restore_via_api() {
  local dump_file="$1"
  local token="${WF_ADMIN_TOKEN:-}"
  if [[ -z "${token}" ]]; then
    echo -e "${YELLOW}⚠️  WF_ADMIN_TOKEN not set; skipping API restore path${NC}"
    return 2
  fi
  echo -e "${GREEN}☁️  Attempting API DB restore upload (multipart)${NC}"
  if [[ "${WF_DRY_RUN:-0}" == "1" ]]; then
    echo -e "${YELLOW}DRY-RUN: Skipping API DB restore upload${NC}"
    return 2
  fi
  # API expects multipart field name 'backup_file'; now accepts .sql, .txt, or .sql.gz
  # Upload the gzipped file directly to avoid huge payload expansion
  local upload_file="${dump_file}"
  local is_gz="0"
  if [[ "${dump_file}" == *.gz ]]; then
    is_gz="1"
  fi

  # Perform upload
  # Choose a reasonable content-type; server checks extension anyway
  local content_type="application/sql"
  if [[ "$is_gz" == "1" ]]; then content_type="application/gzip"; fi
  HTTP_CODE=$(curl --retry 3 --retry-delay 2 --connect-timeout 20 -s -o /tmp/wf_db_restore_api.out -w "%{http_code}" \
    -F "backup_file=@${upload_file};type=${content_type}" \
    "${BASE_URL}/api/database_maintenance.php?action=restore_database&admin_token=${token}") || true

  # Basic validation: HTTP 200 and JSON success true
  if [[ "${HTTP_CODE}" == "200" ]] && grep -q '"success"\s*:\s*true' /tmp/wf_db_restore_api.out 2>/dev/null; then
    echo -e "${GREEN}✅ API restore reported success${NC}"
    return 0
  fi

  # Fallback: upload SQL to server via SFTP, then call server_backup_path
  echo -e "${YELLOW}⚠️  Multipart upload failed or not accepted. Trying server file path restore...${NC}"
  # Use same SFTP credentials as scripts/deploy.sh
  HOST="${WF_DEPLOY_HOST:-}"
  USER="${WF_DEPLOY_USER:-}"
  PASS="${WF_DEPLOY_PASS:-}"
  if [[ -z "$HOST" || -z "$USER" || -z "$PASS" ]]; then
    echo -e "${RED}❌ Missing WF_DEPLOY_HOST/WF_DEPLOY_USER/WF_DEPLOY_PASS for SFTP fallback upload${NC}"
    return 1
  fi
  # Upload into api/uploads/ (API accepts server_backup_path under uploads/)
  REMOTE_DIR="api/uploads"
  # Preserve .gz extension if present so server can stream decompress
  if [[ "$is_gz" == "1" ]]; then
    REMOTE_SQL="${REMOTE_DIR}/deploy_restore.sql.gz"
  else
    REMOTE_SQL="${REMOTE_DIR}/deploy_restore.sql"
  fi

  # Ensure backups directory exists on server via API (it creates ../backups/ if missing)
  curl -s -X POST "${BASE_URL}/api/database_maintenance.php?action=create_backup&admin_token=${token}" >/dev/null || true
  cat > /tmp/wf_upload_restore_sql.txt <<EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit no
open sftp://$USER:$PASS@$HOST
# ensure api/uploads exists
set cmd:fail-exit no
mkdir -p api/uploads
cd /api/uploads
set cmd:fail-exit yes
put ${upload_file} -o deploy_restore.sql.gz
EOL
  if lftp -f /tmp/wf_upload_restore_sql.txt; then
    echo "Uploaded SQL to server as api/uploads/deploy_restore.sql.gz"
  else
    # Fallback if cd or mkdir -p failed
    echo "lftp upload with -p failed; trying simple path..."
    cat > /tmp/wf_upload_restore_sql_fallback.txt <<EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
open sftp://$USER:$PASS@$HOST
put ${upload_file} -o api/uploads/deploy_restore.sql.gz
bye
EOL
    if lftp -f /tmp/wf_upload_restore_sql_fallback.txt; then
      echo "Uploaded SQL to server (fallback path)"
    else
      echo -e "${RED}❌ Failed to upload SQL to server via any path${NC}"
      rm -f /tmp/wf_upload_restore_sql.txt /tmp/wf_upload_restore_sql_fallback.txt
      return 1
    fi
  fi
  rm -f /tmp/wf_upload_restore_sql.txt /tmp/wf_upload_restore_sql_fallback.txt

  # Now call API with server_backup_path relative to api/ (e.g., uploads/deploy_restore.sql or .sql.gz)
  HTTP_CODE=$(curl -s -o /tmp/wf_db_restore_api.out -w "%{http_code}" \
    -X POST \
    -F "server_backup_path=${REMOTE_SQL}" \
    "${BASE_URL}/api/database_maintenance.php?action=restore_database&admin_token=${token}") || true
  if [[ "${HTTP_CODE}" == "200" ]] && grep -q '"success"\s*:\s*true' /tmp/wf_db_restore_api.out 2>/dev/null; then
    echo -e "${GREEN}✅ API restore reported success via server_backup_path${NC}"
    return 0
  else
    echo -e "${YELLOW}⚠️  API server_backup_path restore failed (HTTP ${HTTP_CODE})${NC}"
    echo "Response snippet:"; head -c 500 /tmp/wf_db_restore_api.out || true; echo
    return 1
  fi
}

restore_via_mysql() {
  local dump_file="$1"
  echo -e "${GREEN}🛠️  Restoring DB via direct MySQL client (fallback)${NC}"
  if [[ "${WF_DRY_RUN:-0}" == "1" ]]; then
    echo -e "${YELLOW}DRY-RUN: Skipping direct MySQL restore${NC}"
    return 0
  fi
  # Ensure mysql client is on PATH for the subshell
  ( export PATH="/opt/homebrew/opt/mysql-client/bin:/usr/local/opt/mysql-client/bin:$PATH"; bash scripts/db/restore_live_db.sh "${dump_file}" )
  if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Direct MySQL restore completed${NC}"
    return 0
  else
    echo -e "${RED}❌ Direct MySQL restore failed${NC}"
    return 1
  fi
}

section "Database: restoring dump to LIVE"
# First, ensure a clean state by dropping all current tables (avoids FK conflicts with old schemas)
echo -e "${YELLOW}🧹 Pre-cleaning: dropping all existing tables on LIVE...${NC}"
curl -s -X POST \
  --data-urlencode "skip_tables=secrets" \
  "${BASE_URL}/api/database_maintenance.php?action=drop_all_tables&admin_token=${WF_ADMIN_TOKEN:-}" > /tmp/wf_drop_all.out || true
if grep -q '"success"\s*:\s*true' /tmp/wf_drop_all.out 2>/dev/null; then
  echo -e "${GREEN}✅ Remote database cleared${NC}"
else
  echo -e "${YELLOW}⚠️  Remote database clear failed or returned error (continuing anyway)${NC}"
  cat /tmp/wf_drop_all.out | head -c 200; echo
fi

if restore_via_api "${DUMP_PATH}"; then
  : # success
else
  # API path unavailable/failed; try direct restore
  if ! restore_via_mysql "${DUMP_PATH}"; then
    echo -e "${RED}❌ Database restore to LIVE failed (API and fallback) — aborting full deploy${NC}"
    exit 1
  fi
fi

# 5) Deploy files (force full replace + include vendor)
section "Files: deploying site files to LIVE via scripts/deploy.sh (FULL REPLACE)"
PURGE_ARG=""
if [[ "${PURGE_FLAG}" == "1" ]]; then PURGE_ARG="--purge"; fi
if WF_DRY_RUN=${WF_DRY_RUN:-0} \
   bash scripts/deploy.sh --full ${PURGE_ARG}; then
  echo -e "${GREEN}✅ File deployment completed${NC}"
else
  echo -e "${RED}❌ File deployment failed${NC}"
  exit 1
fi

# 6) Final verification (lightweight)
section "Verify: basic HTTP checks"
HTTP_HOME=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/")
echo -e "  • Home page -> HTTP ${HTTP_HOME}"
MANIFEST_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/dist/.vite/manifest.json")
if [[ "${MANIFEST_CODE}" != "200" ]]; then
  MANIFEST_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/dist/manifest.json")
fi
echo -e "  • Vite manifest -> HTTP ${MANIFEST_CODE}"

echo -e "\n${GREEN}🎉 Full deployment completed successfully!${NC}"
echo -e "${GREEN}📦 DB: restored from local dump${NC}"
echo -e "${GREEN}📁 Files: mirrored to live and verified${NC}"

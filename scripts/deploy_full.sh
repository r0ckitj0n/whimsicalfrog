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

BASE_URL="https://whimsicalfrog.us${WF_PUBLIC_BASE:-}"

section() {
  echo -e "\n============================================================"
  echo -e "== $*"
  echo -e "============================================================\n"
}

# 2) Live backup (best effort)
section "Backup: triggering live website backup"
if curl -s -X POST "${BASE_URL}/api/backup_website.php" >/dev/null; then
  echo -e "${GREEN}✅ Live backup API triggered successfully${NC}"
else
  echo -e "${YELLOW}⚠️  Live backup API call failed (continuing)${NC}"
fi

# 3) Dump LOCAL dev database (gzipped)
section "Database: creating local dev DB dump (gz)"
mkdir -p backups/sql
# Preferred: use mysqldump-based script and capture its reported output path
if DUMP_OUT=$(bash scripts/db/dump_local_db.sh --gzip 2>&1); then
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
  PHP_DUMP_OUT=$(php scripts/db/php_dump_dev.php --gzip 2>&1 || true)
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
  # API expects multipart field name 'backup_file'; only accepts .sql or .txt
  # If we have a .gz, decompress to a temp .sql first
  local upload_file="${dump_file}"
  local tmp_file=""
  if [[ "${dump_file}" == *.gz ]]; then
    tmp_file="/tmp/wf_restore_$(date +%s).sql"
    echo "Decompressing ${dump_file} -> ${tmp_file}"
    gzip -dc "${dump_file}" > "${tmp_file}"
    upload_file="${tmp_file}"
  fi

  # Perform upload
  HTTP_CODE=$(curl -s -o /tmp/wf_db_restore_api.out -w "%{http_code}" \
    -F "backup_file=@${upload_file};type=application/sql" \
    "${BASE_URL}/api/database_maintenance.php?action=restore_database&admin_token=${token}") || true

  # Basic validation: HTTP 200 and JSON success true
  if [[ "${HTTP_CODE}" == "200" ]] && grep -q '"success"\s*:\s*true' /tmp/wf_db_restore_api.out 2>/dev/null; then
    echo -e "${GREEN}✅ API restore reported success${NC}"
    # Clean temp on success
    if [[ -n "${tmp_file}" && -f "${tmp_file}" ]]; then rm -f "${tmp_file}" || true; fi
    return 0
  fi

  # Fallback: upload SQL to server backups/ via SFTP, then call server_backup_path
  echo -e "${YELLOW}⚠️  Multipart upload failed or not accepted. Trying server file path restore...${NC}"
  # Use same SFTP credentials as scripts/deploy.sh
  HOST="home419172903.1and1-data.host"
  USER="acc899014616"
  PASS="Palz2516!"
  # Upload inside api/uploads so server can read it as a relative path without needing to create top-level dirs
  REMOTE_DIR="api/uploads"
  REMOTE_SQL="${REMOTE_DIR}/deploy_restore.sql"

  # Ensure backups directory exists on server via API (it creates ../backups/ if missing)
  curl -s -X POST "${BASE_URL}/api/database_maintenance.php?action=create_backup&admin_token=${token}" >/dev/null || true
  cat > /tmp/wf_upload_restore_sql.txt <<EOL
set sftp:auto-confirm yes
set ssl:verify-certificate no
set cmd:fail-exit no
open sftp://$USER:$PASS@$HOST
# ensure api/uploads exists (ignore errors if exists)
mkdir api
cd api
mkdir uploads
cd /
put ${upload_file} -o ${REMOTE_SQL}
bye
EOL
  if lftp -f /tmp/wf_upload_restore_sql.txt; then
    echo "Uploaded SQL to server as ${REMOTE_SQL}"
  else
    echo -e "${RED}❌ Failed to upload SQL to server backups/${NC}"
    rm -f /tmp/wf_upload_restore_sql.txt
    return 1
  fi
  rm -f /tmp/wf_upload_restore_sql.txt

  # Now call API with server_backup_path relative to api/ (e.g., uploads/deploy_restore.sql)
  HTTP_CODE=$(curl -s -o /tmp/wf_db_restore_api.out -w "%{http_code}" \
    -X POST \
    -F "server_backup_path=${REMOTE_SQL#api/}" \
    "${BASE_URL}/api/database_maintenance.php?action=restore_database&admin_token=${token}") || true
  if [[ "${HTTP_CODE}" == "200" ]] && grep -q '"success"\s*:\s*true' /tmp/wf_db_restore_api.out 2>/dev/null; then
    echo -e "${GREEN}✅ API restore reported success via server_backup_path${NC}"
    # Clean temp on success
    if [[ -n "${tmp_file}" && -f "${tmp_file}" ]]; then rm -f "${tmp_file}" || true; fi
    return 0
  else
    echo -e "${YELLOW}⚠️  API server_backup_path restore failed (HTTP ${HTTP_CODE})${NC}"
    echo "Response snippet:"; head -c 500 /tmp/wf_db_restore_api.out || true; echo
    # Clean temp on failure path as well (no longer needed)
    if [[ -n "${tmp_file}" && -f "${tmp_file}" ]]; then rm -f "${tmp_file}" || true; fi
    return 1
  fi
}

restore_via_mysql() {
  local dump_file="$1"
  echo -e "${GREEN}🛠️  Restoring DB via direct MySQL client (fallback)${NC}"
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
if restore_via_api "${DUMP_PATH}"; then
  : # success
else
  # API path unavailable/failed; try direct restore
  if ! restore_via_mysql "${DUMP_PATH}"; then
    echo -e "${RED}❌ Database restore to LIVE failed (API and fallback) — aborting full deploy${NC}"
    exit 1
  fi
fi

# 5) Deploy files (reuses existing fast deploy which builds and mirrors files)
section "Files: deploying site files to LIVE via scripts/deploy.sh"
if bash scripts/deploy.sh; then
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


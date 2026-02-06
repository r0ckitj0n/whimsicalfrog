#!/usr/bin/env bash
set -euo pipefail

# Applies the FK/collation patch that was uploaded to backups/sql/patch_fix_item_fk.sql
# Usage: bash scripts/db/apply_patch_fix_item_fk.sh

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"

if [[ -f "${ENV_FILE}" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "${ENV_FILE}"
  set +a
fi

if [[ -z "${WF_ADMIN_TOKEN:-}" ]]; then
  echo "WF_ADMIN_TOKEN is not set in .env" >&2
  exit 1
fi

PATCH_PATH="backups/sql/patch_fix_item_fk.sql"
RESTORE_URL="${WF_DEPLOY_BASE_URL:-https://whimsicalfrog.us}/api/restore_db_from_backup.php"

echo "Applying patch via ${RESTORE_URL} ..."
response=$(curl -sS --fail \
  --data-urlencode "file=${PATCH_PATH}" \
  --data-urlencode "admin_token=${WF_ADMIN_TOKEN}" \
  "${RESTORE_URL}")

echo "Response: ${response}"

#!/usr/bin/env bash
set -euo pipefail

# One-time production schema patch:
# - Adds room_settings.icon_vertical_alignment if missing
# - Normalizes existing data to valid values
#
# Usage:
#   bash scripts/db/deploy_room_icon_alignment_schema_patch.sh

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"

if [[ -f "${ENV_FILE}" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "${ENV_FILE}"
  set +a
fi

: "${WF_ADMIN_TOKEN:?WF_ADMIN_TOKEN not set}"

WF_DEPLOY_BASE_URL="${WF_DEPLOY_BASE_URL:-https://whimsicalfrog.us}"
API_URL="${WF_DEPLOY_BASE_URL%/}/api/database_maintenance.php"

SQL_CONTENT=$(cat <<'SQL'
ALTER TABLE `room_settings`
  ADD COLUMN `icon_vertical_alignment` ENUM('top','middle','bottom') NOT NULL DEFAULT 'middle' AFTER `icon_panel_color`;

UPDATE `room_settings`
SET `icon_vertical_alignment` = 'middle'
WHERE `icon_vertical_alignment` IS NULL
   OR `icon_vertical_alignment` NOT IN ('top', 'middle', 'bottom');

ALTER TABLE `room_settings`
  MODIFY COLUMN `icon_vertical_alignment` ENUM('top','middle','bottom') NOT NULL DEFAULT 'middle';
SQL
)

echo "Applying patch via ${API_URL} (action=import_sql)..."
TMP_RESP="/tmp/wf_room_icon_alignment_patch_response.json"
HTTP_CODE=$(curl -sS -o "${TMP_RESP}" -w "%{http_code}" \
  -X POST \
  --data-urlencode "action=import_sql" \
  --data-urlencode "admin_token=${WF_ADMIN_TOKEN}" \
  --data-urlencode "sql_content=${SQL_CONTENT}" \
  "${API_URL}")

echo "HTTP ${HTTP_CODE}"
cat "${TMP_RESP}"
echo

if [[ "${HTTP_CODE}" -lt 200 || "${HTTP_CODE}" -ge 300 ]]; then
  echo "Patch request failed." >&2
  exit 1
fi

echo "Done."

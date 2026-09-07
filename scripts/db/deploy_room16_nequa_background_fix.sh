#!/usr/bin/env bash
set -euo pipefail

# One-time production data patch:
# Room 16 (Ne'Qwa Art Ornaments) storefront background was stuck on an older
# "realistic" AI generation (backgrounds.id=80, plain wood shelves, no Ne'Qwa
# branding) while room_settings.background_url already pointed at a nicer,
# more recent whimsical generation (16-ne-qwa-art-ornaments-room16-624479)
# that was never inserted into the `backgrounds` catalog table nor marked
# active, so the storefront (which reads backgrounds.is_active) never picked
# it up. This patch:
#   1. Inserts a catalog row for the 624479 asset if one doesn't already exist.
#   2. Deactivates all other room 16 backgrounds.
#   3. Activates the 624479 row.
#   4. Re-affirms room_settings.background_url stays in sync.
# Idempotent: safe to re-run.
#
# Usage:
#   bash scripts/db/deploy_room16_nequa_background_fix.sh

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"

if [[ -f "${ENV_FILE}" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "${ENV_FILE}"
  set +a
fi

: "${WF_ADMIN_TOKEN:?WF_ADMIN_TOKEN not set}"

: "${WF_DEPLOY_BASE_URL:?WF_DEPLOY_BASE_URL not set (e.g. the live site base URL)}"
API_URL="${WF_DEPLOY_BASE_URL%/}/api/database_maintenance.php"

SQL_CONTENT=$(cat <<'SQL'
INSERT INTO `backgrounds` (`room_number`, `name`, `image_filename`, `png_filename`, `webp_filename`, `is_active`, `theme`, `created_at`)
SELECT '16', "16 - Ne'Qwa Art Ornaments 20260222-192158 (restored)", 'backgrounds/16-ne-qwa-art-ornaments-room16-624479.png', 'backgrounds/16-ne-qwa-art-ornaments-room16-624479.png', 'backgrounds/16-ne-qwa-art-ornaments-room16-624479.webp', 0, 'whimsical', '2026-02-22 19:21:58'
WHERE NOT EXISTS (
  SELECT 1 FROM `backgrounds` WHERE `room_number` = '16' AND `webp_filename` = 'backgrounds/16-ne-qwa-art-ornaments-room16-624479.webp'
);

UPDATE `backgrounds` SET `is_active` = 0 WHERE `room_number` = '16';

UPDATE `backgrounds` SET `is_active` = 1 WHERE `room_number` = '16' AND `webp_filename` = 'backgrounds/16-ne-qwa-art-ornaments-room16-624479.webp';

UPDATE `room_settings` SET `background_url` = '/images/backgrounds/16-ne-qwa-art-ornaments-room16-624479.webp' WHERE `room_number` = '16';
SQL
)

echo "Applying patch via ${API_URL} (action=import_sql)..."
TMP_RESP="/tmp/wf_room16_nequa_background_patch_response.json"
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

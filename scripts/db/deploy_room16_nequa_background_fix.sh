#!/usr/bin/env bash
set -euo pipefail

# Production data patch (v2 -- supersedes the v1 run from earlier today):
#
# v1 (earlier today) activated backgrounds.id=118 (16-ne-qwa-art-ornaments-
# room16-624479), reasoning it was the "nicer" of the room's stored options.
# That was wrong: its own AI generation prompt (ai_generation_history id=23)
# literally reads "A high-quality modern 3D children's cartoon animation
# render (Pixar-esque)" -- exactly the cartoon look the stakeholder does not
# want, and exactly what he reported seeing again after that deploy.
#
# This v2 patch instead activates backgrounds.id=105 ("Ne Qwa Art Cabin
# Interior (Realistic)"), a photorealistic rustic-cabin scene. Unlike every
# other room-16 background, id=105 has no matching ai_generation_history row
# at all -- it was not produced by the cartoon-style AI template, and its
# created_at (2026-07-03) is by far the most recent of the room's assets,
# consistent with it being the stakeholder's own more recent upload that had
# never actually been activated (so it never showed on the storefront).
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
UPDATE `backgrounds` SET `is_active` = 0 WHERE `room_number` = '16';

UPDATE `backgrounds` SET `is_active` = 1 WHERE `room_number` = '16' AND `webp_filename` = 'backgrounds/realistic/realistic-room16.webp';

UPDATE `room_settings` SET `background_url` = '/images/backgrounds/realistic/realistic-room16.webp' WHERE `room_number` = '16';
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

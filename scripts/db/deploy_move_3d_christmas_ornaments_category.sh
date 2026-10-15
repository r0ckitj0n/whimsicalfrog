#!/usr/bin/env bash
set -euo pipefail

# Move items whose name starts with "3D Christmas Ornament" into the
# "3D Christmas Ornaments" category (category text + category_id).
# Idempotent. Uses import_sql (not restore_database) so tables are not wiped.
#
# Usage:
#   bash scripts/db/deploy_move_3d_christmas_ornaments_category.sh

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"

if [[ -f "${ENV_FILE}" ]]; then
  set -a
  # shellcheck disable=SC1090
  . "${ENV_FILE}"
  set +a
fi

: "${WF_ADMIN_TOKEN:?WF_ADMIN_TOKEN not set}"
: "${WF_DEPLOY_BASE_URL:?WF_DEPLOY_BASE_URL not set}"

API_URL="${WF_DEPLOY_BASE_URL%/}/api/database_maintenance.php"

SQL_CONTENT=$(cat <<'SQL'
UPDATE items i
INNER JOIN categories c ON c.name = '3D Christmas Ornaments'
SET
  i.category = '3D Christmas Ornaments',
  i.category_id = c.id
WHERE i.name LIKE '3D Christmas Ornament%'
  AND (
    i.category_id IS NULL
    OR i.category_id <> c.id
    OR IFNULL(i.category, '') <> '3D Christmas Ornaments'
  );
SQL
)

echo "Applying category move via ${API_URL} (action=import_sql)..."
TMP_RESP="/tmp/wf_move_3d_ornaments_patch_response.json"
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

# Fail if import reported statement warnings
python3 - <<'PY'
import json
resp = json.load(open("/tmp/wf_move_3d_ornaments_patch_response.json"))
if not resp.get("success"):
    raise SystemExit(f"Patch unsuccessful: {resp}")
warnings = resp.get("warnings")
if warnings:
    raise SystemExit(f"Patch reported warnings: {warnings}")
print(f"statements_executed={resp.get('statements_executed')} rows_affected={resp.get('rows_affected')}")
PY

echo "Verifying live sample SKUs..."
VERIFY_RESP=$(curl -sS -X POST "${WF_DEPLOY_BASE_URL%/}/api/get_items.php" \
  -H 'Content-Type: application/json' \
  -d '{"item_ids":["WF-CH-031","WF-CH-130","WF-3DC-0001"]}')
echo "${VERIFY_RESP}" | tee /tmp/wf_move_3d_ornaments_verify.json
echo

python3 - <<'PY'
import json
data = json.load(open("/tmp/wf_move_3d_ornaments_verify.json"))
items = data.get("data") or []
if not items:
    raise SystemExit("Verification failed: no items returned")
bad = [i for i in items if i.get("category") != "3D Christmas Ornaments"]
for i in items:
    print(f"{i.get('sku')} -> {i.get('category')}")
if bad:
    raise SystemExit(f"Verification failed: {len(bad)} item(s) still misfiled")
print("Verification OK")
PY

echo "Done."

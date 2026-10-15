#!/usr/bin/env bash
set -euo pipefail

# Deduplicate "3D Christmas Ornament*" inventory:
# - True duplicate SKU pairs exist (WF-CH-* Christmas-origin + WF-3DC-*).
# - Items are single-category (not dual-tagged).
# - Merge Christmas-origin product data into WF-3DC keepers, then archive WF-CH duplicates.
#
# Usage:
#   bash scripts/db/deploy_dedupe_3d_christmas_ornaments.sh

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
UPDATE items AS dest
INNER JOIN items AS src
  ON src.name = dest.name
 AND src.sku LIKE 'WF-CH-%'
 AND dest.sku LIKE 'WF-3DC-%'
 AND dest.name LIKE '3D Christmas Ornament%'
 AND dest.is_archived = 0
 AND src.is_archived = 0
INNER JOIN categories AS cat
  ON cat.name = '3D Christmas Ornaments'
SET
  dest.description = CASE
    WHEN dest.description IS NULL OR dest.description = '' OR dest.description LIKE '3D printed Christmas ornament%'
      THEN src.description
    ELSE dest.description
  END,
  dest.retail_price = CASE
    WHEN IFNULL(dest.retail_price, 0) = 0 OR IFNULL(src.retail_price, 0) > IFNULL(dest.retail_price, 0)
      THEN src.retail_price
    ELSE dest.retail_price
  END,
  dest.cost_price = CASE
    WHEN IFNULL(dest.cost_price, 0) = 0 OR IFNULL(src.cost_price, 0) > IFNULL(dest.cost_price, 0)
      THEN src.cost_price
    ELSE dest.cost_price
  END,
  dest.stock_quantity = CASE
    WHEN IFNULL(dest.stock_quantity, 0) = 0 OR IFNULL(src.stock_quantity, 0) > IFNULL(dest.stock_quantity, 0)
      THEN src.stock_quantity
    ELSE dest.stock_quantity
  END,
  dest.reorder_point = CASE
    WHEN IFNULL(dest.reorder_point, 0) = 0 THEN src.reorder_point
    ELSE dest.reorder_point
  END,
  dest.image_url = COALESCE(NULLIF(dest.image_url, ''), src.image_url),
  dest.status = IF(src.status = 'live', 'live', dest.status),
  dest.weight_oz = COALESCE(dest.weight_oz, src.weight_oz),
  dest.package_length_in = COALESCE(dest.package_length_in, src.package_length_in),
  dest.package_width_in = COALESCE(dest.package_width_in, src.package_width_in),
  dest.package_height_in = COALESCE(dest.package_height_in, src.package_height_in),
  dest.category = '3D Christmas Ornaments',
  dest.category_id = cat.id;

INSERT INTO item_images (
  sku, image_path, is_primary, sort_order, alt_text, media_type,
  ai_description, processed_with_ai, original_path, processing_date, ai_trim_data
)
SELECT
  dest.sku,
  ii.image_path,
  ii.is_primary,
  ii.sort_order,
  ii.alt_text,
  ii.media_type,
  ii.ai_description,
  ii.processed_with_ai,
  ii.original_path,
  ii.processing_date,
  ii.ai_trim_data
FROM item_images ii
INNER JOIN items src
  ON src.sku = ii.sku
 AND src.name LIKE '3D Christmas Ornament%'
 AND src.sku LIKE 'WF-CH-%'
 AND src.is_archived = 0
INNER JOIN items dest
  ON dest.name = src.name
 AND dest.sku LIKE 'WF-3DC-%'
 AND dest.is_archived = 0
WHERE NOT EXISTS (
  SELECT 1 FROM item_images x WHERE x.sku = dest.sku AND x.image_path = ii.image_path
);

UPDATE items
SET
  is_archived = 1,
  archived_at = NOW(),
  archived_by = 'dedupe_3d_christmas_ornaments',
  is_active = 0
WHERE name LIKE '3D Christmas Ornament%'
  AND sku LIKE 'WF-CH-%'
  AND is_archived = 0
  AND EXISTS (
    SELECT 1 FROM (
      SELECT name FROM items WHERE sku LIKE 'WF-3DC-%' AND is_archived = 0 AND name LIKE '3D Christmas Ornament%'
    ) AS keepers
    WHERE keepers.name = items.name
  );
SQL
)

echo "Applying dedupe via ${API_URL} (action=import_sql)..."
TMP_RESP="/tmp/wf_dedupe_3d_ornaments_patch_response.json"
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

python3 - <<'PY'
import json
resp = json.load(open("/tmp/wf_dedupe_3d_ornaments_patch_response.json"))
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
  -d '{"item_ids":["WF-CH-031","WF-3DC-0001","WF-CH-130","WF-3DC-0100"]}')
echo "${VERIFY_RESP}" | tee /tmp/wf_dedupe_3d_ornaments_verify.json
echo

python3 - <<'PY'
import json
data = json.load(open("/tmp/wf_dedupe_3d_ornaments_verify.json"))
items = {i["sku"]: i for i in (data.get("data") or [])}
keeper = items.get("WF-3DC-0001")
if not keeper:
    raise SystemExit("Verification failed: WF-3DC-0001 missing from API")
if keeper.get("category") != "3D Christmas Ornaments":
    raise SystemExit(f"Keeper wrong category: {keeper.get('category')}")
if float(keeper.get("retail_price") or 0) <= 0:
    raise SystemExit("Keeper still has zero retail_price after merge")
if not keeper.get("image_url"):
    raise SystemExit("Keeper still missing image_url after merge")
# Archived items may or may not appear in get_items; either is OK if keeper is good.
print(f"WF-3DC-0001 -> category={keeper.get('category')} price={keeper.get('retail_price')} stock={keeper.get('stock_quantity')} img={keeper.get('image_url')}")
if "WF-CH-031" in items:
    print(f"NOTE: WF-CH-031 still returned by get_items (may not filter archived): category={items['WF-CH-031'].get('category')}")
print("Verification OK")
PY

echo "Done."

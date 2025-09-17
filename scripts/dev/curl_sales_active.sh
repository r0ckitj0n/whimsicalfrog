#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
SKU="${1:-WF-GEN-001A}"

curl -s -S "$BASE/api/sales.php?action=get_active_sales&item_sku=$SKU" | jq .

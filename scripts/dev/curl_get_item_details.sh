#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
SKU="${1:-WF-GEN-001A}"

curl -s -S "$BASE/api/get_item_details.php?sku=$SKU" | jq .

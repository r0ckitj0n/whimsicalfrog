#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
SKU="${1:-WF-AR-001}"
TOKEN="${WF_ADMIN_TOKEN:-whimsical_admin_2024}"

curl -s -S "$BASE/api/get_marketing_suggestion.php?sku=$SKU&admin_token=$TOKEN" | jq .

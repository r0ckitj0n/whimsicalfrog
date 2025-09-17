#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ZIP="${1:-30301}"
METHOD="${2:-USPS}"

curl -s -S -X POST "$BASE/api/checkout_pricing.php" \
  -H 'Content-Type: application/json' \
  --data "{\"itemIds\":[],\"quantities\":[],\"shippingMethod\":\"$METHOD\",\"zip\":\"$ZIP\",\"debug\":true}" | jq .

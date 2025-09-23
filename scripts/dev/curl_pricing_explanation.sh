#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
TEXT="${1:-Based on market research and competitor pricing, with value-based adjustments}"

curl -s -S --get \
  --data-urlencode "text=$TEXT" \
  "$BASE/api/get_pricing_explanation.php" | jq .

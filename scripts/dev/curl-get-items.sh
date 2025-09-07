#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
CATEGORY="${1:-}"
if [[ -n "$CATEGORY" ]]; then
  curl -s -S "$BASE/api/get_items.php?category=$CATEGORY" | jq .
else
  curl -s -S "$BASE/api/get_items.php" | jq .
fi

#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
PROVIDER="${1:-all}"
TOKEN="${WF_ADMIN_TOKEN:-whimsical_admin_2024}"

if [[ "$PROVIDER" == "all" ]]; then
  curl -s -S "$BASE/api/get_ai_models.php?provider=all&admin_token=$TOKEN" | jq .
else
  curl -s -S "$BASE/api/get_ai_models.php?provider=$PROVIDER&admin_token=$TOKEN" | jq .
fi

#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ACTION="${1:-list_all}"
PAGE="${2:-settings}"
TOKEN="${WF_ADMIN_TOKEN:-whimsical_admin_2024}"

case "$ACTION" in
  list_all)
    curl -s -S -H 'Content-Type: application/json' \
      -X GET "$BASE/api/help_tooltips.php?action=list_all&admin_token=$TOKEN" | jq .
    ;;
  get_stats)
    curl -s -S -H 'Content-Type: application/json' \
      -X GET "$BASE/api/help_tooltips.php?action=get_stats&admin_token=$TOKEN" | jq .
    ;;
  set_global_enabled)
    PAYLOAD=$(jq -n --argjson enabled true '{enabled: $enabled, admin_token: "'$TOKEN'"}')
    curl -s -S -H 'Content-Type: application/json' \
      -X POST "$BASE/api/help_tooltips.php?action=set_global_enabled" \
      --data "$PAYLOAD" | jq .
    ;;
  *)
    echo "Unknown ACTION. Supported: list_all | get_stats | set_global_enabled" >&2
    exit 1
    ;;
 esac

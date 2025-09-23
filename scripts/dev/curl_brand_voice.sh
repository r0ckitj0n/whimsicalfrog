#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ACTION="${1:-get_active}"
TOKEN="${WF_ADMIN_TOKEN:-whimsical_admin_2024}"

case "$ACTION" in
  get_active)
    curl -s -S "$BASE/api/brand_voice_options.php?action=get_active&admin_token=$TOKEN" | jq .
    ;;
  get_all)
    curl -s -S "$BASE/api/brand_voice_options.php?action=get_all&admin_token=$TOKEN" | jq .
    ;;
  initialize_defaults)
    curl -s -S "$BASE/api/brand_voice_options.php?action=initialize_defaults&admin_token=$TOKEN" | jq .
    ;;
  *)
    echo "Unknown ACTION. Supported: get_active | get_all | initialize_defaults" >&2
    exit 1
    ;;
 esac

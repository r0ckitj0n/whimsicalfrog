#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ACTION="${1:-get_current}"
PROVIDER="${2:-}"
MODEL="${3:-}"

case "$ACTION" in
  get_current)
    curl -s -S "$BASE/api/get_ai_model_capabilities.php?action=get_current" | jq .
    ;;
  get_all)
    curl -s -S "$BASE/api/get_ai_model_capabilities.php?action=get_all" | jq .
    ;;
  supports_images)
    Q="action=supports_images"
    [[ -n "$PROVIDER" ]] && Q+="&provider=$PROVIDER"
    [[ -n "$MODEL" ]] && Q+="&model=$MODEL"
    curl -s -S "$BASE/api/get_ai_model_capabilities.php?$Q" | jq .
    ;;
  *)
    echo "Unknown action. Use: get_current | get_all | supports_images [provider] [model]" >&2
    exit 1
    ;;
 esac

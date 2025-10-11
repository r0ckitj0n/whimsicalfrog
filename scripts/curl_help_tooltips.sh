#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ACTION="${1:-get}"
PAGE="${2:-settings}"

if [[ "$ACTION" == "get_pages" ]]; then
  curl -s -S "$BASE/api/help_tooltips.php?action=get_pages" | jq .
else
  curl -s -S "$BASE/api/help_tooltips.php?action=$ACTION&page_context=$PAGE" | jq .
fi

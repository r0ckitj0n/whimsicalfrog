#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ROOM="${1:-1}"
ACTIVE_ONLY="${2:-false}"

curl -s -S "$BASE/api/backgrounds.php?room=$ROOM&active_only=$ACTIVE_ONLY" | jq .

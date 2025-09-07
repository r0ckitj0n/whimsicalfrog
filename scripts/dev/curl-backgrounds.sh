#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ROOM_TYPE="${1:-room1}"
ACTIVE_ONLY="${2:-false}"

curl -s -S "$BASE/api/backgrounds.php?room_type=$ROOM_TYPE&active_only=$ACTIVE_ONLY" | jq .

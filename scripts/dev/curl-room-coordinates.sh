#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ROOM_TYPE="${1:-room1}"

curl -s -S "$BASE/api/get_room_coordinates.php?room_type=$ROOM_TYPE" | jq .

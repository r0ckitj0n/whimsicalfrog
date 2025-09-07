#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"
ROOM="${1:-1}"

curl -s -S "$BASE/api/get_room_coordinates.php?room=$ROOM" | jq .

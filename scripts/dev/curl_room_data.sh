#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"

curl -s -S "$BASE/api/get_room_data.php" | jq .

#!/usr/bin/env bash
set -euo pipefail
BASE="http://localhost:8080"

curl -s -S "$BASE/api/get_categories.php" | jq .
